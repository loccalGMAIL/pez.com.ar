<?php

namespace App\Filament\Pages;

use App\Support\Settings as AppSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Defaults proposed for new quotes. Quotes keep their own copy, so changing
 * these never alters quotes already made.
 */
class Settings extends Page
{
    /**
     * Keys stored by this page, as in config/pez.php.
     */
    private const KEYS = [
        'quotes.valid_days',
        'quotes.default_warranty',
        'quotes.default_payment_terms',
        'quotes.default_delivery_time',
        'quotes.default_terms',
        'quotes.installments',
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

        $data['quotes_installments'] = collect($data['quotes_installments'] ?? [])
            ->map(fn (array $plan) => ['count' => (int) $plan['count'], 'surcharge' => (float) $plan['surcharge']])
            ->sortBy('count')
            ->values()
            ->all();
        $data['quotes_valid_days'] = (int) $data['quotes_valid_days'];

        AppSettings::set(
            collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => $data[self::field($key)] ?? null])->all(),
        );

        Notification::make()
            ->title('Configuración guardada')
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
