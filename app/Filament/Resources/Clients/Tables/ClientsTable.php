<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\TaxCondition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('business_name')
            ->columns([
                TextColumn::make('business_name')
                    ->label('Razón social')
                    ->description(fn ($record) => $record->trade_name)
                    ->searchable(['business_name', 'trade_name'])
                    ->sortable(),
                TextColumn::make('cuit')
                    ->label('CUIT')
                    // The column holds digits only; accept searches typed with dashes.
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('cuit', 'like', '%'.preg_replace('/\D/', '', $search).'%')),
                TextColumn::make('tax_condition')
                    ->label('IVA')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(),
                TextColumn::make('city')
                    ->label('Localidad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Activo'),
                SelectFilter::make('tax_condition')
                    ->label('Condición frente al IVA')
                    ->options(TaxCondition::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
