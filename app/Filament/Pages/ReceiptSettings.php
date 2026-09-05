<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminSupport;
use App\Models\Branch;
use App\Services\ReceiptProfileResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceiptSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationLabel = 'Receipt Settings';

    protected static ?string $title = 'Branch Receipt Settings';

    protected string $view = 'filament.pages.receipt-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.settings');
    }

    public function mount(): void
    {
        $company = AdminSupport::company();

        abort_unless($company, 403, 'A company assignment is required to edit receipt settings.');

        $branches = $this->receiptBranchOptions();
        $branchId = AdminSupport::user()?->branch_id ?? array_key_first($branches);
        abort_unless($branchId, 403, 'A branch assignment is required to edit receipt settings.');

        $this->fillBranchReceiptForm($branchId);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Identity')
                    ->description('Each branch can have its own receipt identity. Its branch address and phone are printed from the Branch record.')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn (): array => $this->receiptBranchOptions())
                            ->live()
                            ->required(),
                        TextInput::make('receipt_shop_name')
                            ->label('Receipt Shop Name')
                            ->helperText('This is the heading printed on this branch’s receipts.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('receipt_gst_label')
                            ->label('GST Label')
                            ->default('GST No.')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('receipt_tax_number')
                            ->label('GST Number')
                            ->maxLength(255),
                        Grid::make(2)->schema([
                            Toggle::make('receipt_show_address')->label('Print address')->default(true),
                            Toggle::make('receipt_show_phone')->label('Print phone')->default(true),
                        ]),
                    ]),
                Section::make('Receipt Messages')
                    ->schema([
                        Textarea::make('receipt_header')
                            ->label('Header Message')
                            ->helperText('Printed below the shop details.')
                            ->rows(3)
                            ->maxLength(1000),
                        Textarea::make('receipt_footer')
                            ->label('Footer Message')
                            ->helperText('Printed at the bottom of each receipt.')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $company = AdminSupport::company();

        abort_unless($company, 403);
        $state = $this->form->getState();
        $branch = Branch::query()->where('company_id', $company->id)->findOrFail($state['branch_id']);
        $branch->update([
            'receipt_shop_name' => $state['receipt_shop_name'],
            'receipt_tax_number' => $state['receipt_tax_number'] ?: null,
            'receipt_gst_label' => $state['receipt_gst_label'],
            'receipt_header' => $state['receipt_header'] ?: null,
            'receipt_footer' => $state['receipt_footer'] ?: null,
            'receipt_show_address' => $state['receipt_show_address'],
            'receipt_show_phone' => $state['receipt_show_phone'],
        ]);

        Notification::make()->title('Receipt settings saved')->success()->send();
    }

    public function updatedDataBranchId(?string $branchId): void
    {
        if ($branchId) {
            $this->fillBranchReceiptForm($branchId);
        }
    }

    private function fillBranchReceiptForm(string $branchId): void
    {
        $company = AdminSupport::company();
        $branch = Branch::query()->where('company_id', $company?->id)->findOrFail($branchId);
        $profile = app(ReceiptProfileResolver::class)->resolve($company, $branch);

        $this->form->fill([
            'branch_id' => $branch->id,
            'receipt_shop_name' => $branch->receipt_shop_name ?: $profile['shop_name'],
            'receipt_tax_number' => $branch->receipt_tax_number ?: $profile['tax_number'],
            'receipt_gst_label' => $branch->receipt_gst_label ?: $profile['gst_label'],
            'receipt_header' => $branch->receipt_header ?: $profile['header'],
            'receipt_footer' => $branch->receipt_footer ?: $profile['footer'],
            'receipt_show_address' => $profile['show_address'],
            'receipt_show_phone' => $profile['show_phone'],
        ]);
    }

    /** @return array<string, string> */
    private function receiptBranchOptions(): array
    {
        $company = AdminSupport::company();

        return $company
            ? $company->branches()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
            : [];
    }
}
