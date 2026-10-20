<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\HomeContent;
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
 * Admin-editable community home page content (hero text in Bangla + English
 * and community links). Featured frames are curated on "Generated frames".
 *
 * @property-read Schema $form
 */
class HomeSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Home page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(HomeContent::get());
    }

    public function form(Schema $schema): Schema
    {
        $localized = fn (string $key, string $label, bool $long = false) => Section::make($label)
            ->columns(2)
            ->schema([
                ($long ? Textarea::make("{$key}.bn")->rows(4) : TextInput::make("{$key}.bn"))->label('বাংলা')->required()->maxLength($long ? 1000 : 200),
                ($long ? Textarea::make("{$key}.en")->rows(4) : TextInput::make("{$key}.en"))->label('English')->required()->maxLength($long ? 1000 : 200),
            ]);

        return $schema
            ->statePath('data')
            ->components([
                $localized('title', 'Title'),
                $localized('tagline', 'Tagline'),
                $localized('intro', 'Introduction', long: true),
                Section::make('Community links')
                    ->description('Shown on the home page (e.g. Facebook group, Local Guides Connect).')
                    ->schema([
                        Repeater::make('links')
                            ->hiddenLabel()
                            ->columns(2)
                            ->maxItems(8)
                            ->reorderable()
                            ->schema([
                                TextInput::make('label')->required()->maxLength(60),
                                TextInput::make('url')->required()->url()->startsWith(['https://'])->maxLength(500),
                            ]),
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
                        Action::make('save')->label('Save')->submit('save'),
                        Action::make('view')->label('View home page')->color('gray')->url(route('home'), shouldOpenInNewTab: true),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['links'] = array_values($data['links'] ?? []);

        Setting::put(HomeContent::KEY, $data);

        Notification::make()->title('Home page updated')->success()->send();
    }
}
