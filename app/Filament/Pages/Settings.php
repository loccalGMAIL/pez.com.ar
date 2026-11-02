<?php

namespace App\Filament\Pages;

use App\Enums\InvoiceType;
use App\Rules\Cuit;
use App\Support\Arca\ArcaException;
use App\Support\Arca\Wsfe;
use App\Support\Settings as AppSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\ConnectionException;
use UnitEnum;

/**
 * Company data printed on documents and defaults proposed for new quotes.
 * Quotes keep their own copy of conditions and installment amounts, so
 * changing these never alters quotes already made.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    /**
     * Keys stored by this page, as in config/pez.php.
     */
    private const KEYS = [
        'company.name',
        'company.owner',
        'company.cuit',
        'company.phone',
        'company.email',
        'company.website',
        'company.address',
        'company.slogan',
        'quotes.valid_days',
        'quotes.default_markup',
        'quotes.default_warranty',
        'quotes.default_payment_terms',
        'quotes.default_delivery_time',
        'quotes.default_terms',
        'quotes.installments',
        'billing.due_days',
        'invoicing.enabled',
        'invoicing.point_of_sale',
        'invoicing.legal_name',
        'invoicing.address',
        'invoicing.gross_income',
        'invoicing.activity_start',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración';

    protected static ?string $slug = 'configuracion';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(
            collect(self::KEYS)->mapWithKeys(fn (string $key) => [self::field($key) => AppSettings::get($key)])->all(),
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Empresa')
                    ->description('Datos que salen en el encabezado y el pie de los presupuestos, y en los mensajes a clientes.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_owner')
                            ->label('Responsable')
                            ->helperText('Se imprime en el pie del PDF.')
                            ->maxLength(255),
                        TextInput::make('company_cuit')
                            ->label('CUIT')
                            ->mask('99-99999999-9')
                            ->required(fn (Get $get) => (bool) $get('invoicing_enabled'))
                            ->rule(new Cuit),
                        TextInput::make('company_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('company_email')
                            ->label('Email')
                            ->helperText('Las respuestas a los mails de presupuestos llegan acá.')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('company_website')
                            ->label('Sitio web')
                            ->maxLength(255),
                        TextInput::make('company_address')
                            ->label('Dirección')
                            ->maxLength(255),
                        TextInput::make('company_slogan')
                            ->label('Frase')
                            ->maxLength(255),
                    ]),
                Section::make('Cuotas')
                    ->description('Planes que se proponen al activar "Ofrecer cuotas" en un presupuesto. Cada presupuesto guarda sus importes: cambiar estos recargos no modifica los ya hechos.')
                    ->schema([
                        Repeater::make('quotes_installments')
                            ->hiddenLabel()
                            ->addActionLabel('Agregar plan')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columns(2)
                            ->schema([
                                TextInput::make('count')
                                    ->label('Cuotas')
                                    ->integer()
                                    ->minValue(2)
                                    ->maxValue(48)
                                    ->required()
                                    ->distinct(),
                                TextInput::make('surcharge')
                                    ->label('Recargo')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('%')
                                    ->default(0)
                                    ->required(),
                            ]),
                    ]),
                Section::make('Cobranza')
                    ->schema([
                        TextInput::make('billing_due_days')
                            ->label('Plazo de pago de los cargos')
                            ->helperText('Días desde que se genera un cargo (abonos, presupuestos, órdenes de trabajo) hasta su vencimiento.')
                            ->integer()
                            ->minValue(0)
                            ->suffix('días')
                            ->required(),
                    ]),
                Section::make('Facturación electrónica (ARCA)')
                    ->key('invoicing')
                    ->description(fn () => self::invoicingDescription())
                    ->columns(2)
                    ->headerActions([
                        Action::make('testArcaConnection')
                            ->label('Probar conexión')
                            ->icon(Heroicon::OutlinedSignal)
                            ->color('gray')
                            ->tooltip('Usa los datos guardados')
                            ->action(fn () => self::testArcaConnection()),
                    ])
                    ->schema([
                        Toggle::make('invoicing_enabled')
                            ->label('Emitir facturas desde los recibos')
                            ->helperText('Factura C. Es opcional en cada pago: solo se factura lo que elijas.')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('invoicing_point_of_sale')
                            ->label('Punto de venta')
                            ->helperText('El de tipo "RECE para aplicativo y web services", no el de Comprobantes en línea.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(99999)
                            ->required(fn (Get $get) => (bool) $get('invoicing_enabled')),
                        TextInput::make('invoicing_legal_name')
                            ->label('Nombre o razón social')
                            ->helperText('Como figura en ARCA. Si queda vacío se usa el responsable.')
                            ->maxLength(255),
                        TextInput::make('invoicing_address')
                            ->label('Domicilio comercial')
                            ->helperText('Si queda vacío se usa la dirección de la empresa.')
                            ->maxLength(255),
                        TextInput::make('invoicing_gross_income')
                            ->label('Ingresos Brutos')
                            ->maxLength(255),
                        DatePicker::make('invoicing_activity_start')
                            ->label('Inicio de actividades'),
                    ]),
                Section::make('Presupuestos')
                    ->description('Valores con los que arranca cada presupuesto nuevo.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('quotes_valid_days')
                            ->label('Validez')
                            ->integer()
                            ->minValue(1)
                            ->suffix('días')
                            ->required(),
                        TextInput::make('quotes_default_markup')
                            ->label('Recargo sobre el costo')
                            ->helperText('Se propone al cargar el costo de un ítem; se puede cambiar en cada uno.')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('quotes_default_warranty')
                            ->label('Garantía')
                            ->maxLength(255),
                        TextInput::make('quotes_default_payment_terms')
                            ->label('Forma de pago')
                            ->maxLength(255),
                        TextInput::make('quotes_default_delivery_time')
                            ->label('Plazo de entrega')
                            ->maxLength(255),
                        Textarea::make('quotes_default_terms')
                            ->label('Nota al pie')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Guardar')
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $plans = $data['quotes_installments'] ?? [];

        $data['quotes_installments'] = collect(is_array($plans) ? $plans : [])
            ->map(fn (array $plan) => ['count' => (int) $plan['count'], 'surcharge' => (float) $plan['surcharge']])
            ->sortBy('count')
            ->values()
            ->all();
        $data['quotes_valid_days'] = (int) $data['quotes_valid_days'];
        $data['quotes_default_markup'] = (float) $data['quotes_default_markup'];
        $data['billing_due_days'] = (int) $data['billing_due_days'];
        $data['invoicing_enabled'] = (bool) ($data['invoicing_enabled'] ?? false);
        $data['invoicing_point_of_sale'] = blank($data['invoicing_point_of_sale'] ?? null) ? null : (int) $data['invoicing_point_of_sale'];

        AppSettings::set(
            collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => $data[self::field($key)] ?? null])->all(),
        );

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }

    private static function invoicingDescription(): string
    {
        $environment = config('arca.environment') === 'produccion'
            ? 'Producción.'
            : 'Homologación: facturas de prueba, sin validez fiscal.';
        $certificate = is_readable((string) config('arca.certificate')) && is_readable((string) config('arca.key'))
            ? 'Certificado cargado.'
            : 'Falta el certificado o la clave (ver ARCA_CERT_PATH y ARCA_KEY_PATH en el .env).';

        return "Ambiente: {$environment} {$certificate}";
    }

    /**
     * Checks ARCA's servers, the certificate and the point of sale with the saved settings.
     */
    private static function testArcaConnection(): void
    {
        $pointOfSale = AppSettings::invoicing()['point_of_sale'];

        try {
            $wsfe = app(Wsfe::class);
            $status = $wsfe->serverStatus();

            if (collect($status)->contains(fn (string $value) => $value !== 'OK')) {
                throw new ArcaException('ARCA informa problemas en sus servidores: '.collect($status)->map(fn ($value, $key) => "{$key} {$value}")->join(', '));
            }

            if ($pointOfSale === null) {
                throw new ArcaException('Los servidores de ARCA responden. Guardá el punto de venta para probar el certificado.');
            }

            $last = $wsfe->lastAuthorized($pointOfSale, InvoiceType::FacturaC);
        } catch (ArcaException $exception) {
            Notification::make()->title('No se pudo verificar la conexión')->body($exception->getMessage())->danger()->persistent()->send();

            return;
        } catch (ConnectionException) {
            Notification::make()->title('ARCA no respondió')->body('Probá de nuevo en unos minutos.')->danger()->send();

            return;
        }

        Notification::make()
            ->title('Conexión con ARCA correcta')
            ->body("Última Factura C autorizada en el punto de venta {$pointOfSale}: ".($last === 0 ? 'ninguna' : "N.º {$last}").'.')
            ->success()
            ->send();
    }

    /**
     * Form field for a settings key: dots would nest the form state.
     */
    private static function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
