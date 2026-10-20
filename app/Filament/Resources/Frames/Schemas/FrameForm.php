<?php

namespace App\Filament\Resources\Frames\Schemas;

use App\Models\Frame;
use App\Rules\ValidFrameConfig;
use App\Services\Frames\FrameRenderer;
use Database\Factories\FrameFactory;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class FrameForm
{
    public const PRESETS = [
        '1080x1920' => 'Story / status — 1080 × 1920',
        '1080x1350' => 'Feed post — 1080 × 1350',
        '1080x1080' => 'Square — 1080 × 1080',
        'custom' => 'Custom size',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make('Frame')
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(200)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set, Get $get, ?string $state, ?Frame $record) => $record === null && blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                            TextInput::make('slug')
                                ->required()
                                ->alphaDash()
                                ->maxLength(120)
                                ->unique(ignoreRecord: true)
                                ->helperText('Public editor link: /frames/{slug}/create'),
                            Select::make('event_id')
                                ->relationship('event', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                    TextInput::make('slug')->required()->alphaDash()->unique('events', 'slug'),
                                ]),
                            Select::make('size_preset')
                                ->label('Size')
                                ->options(self::PRESETS)
                                ->default('1080x1920')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(function (Select $component, ?Frame $record) {
                                    if ($record) {
                                        $key = "{$record->size_w}x{$record->size_h}";
                                        $component->state(array_key_exists($key, self::PRESETS) ? $key : 'custom');
                                    }
                                })
                                ->afterStateUpdated(function (?string $state, Set $set) {
                                    if ($state && $state !== 'custom') {
                                        [$w, $h] = array_map('intval', explode('x', $state));
                                        $set('size_w', $w);
                                        $set('size_h', $h);
                                    }
                                }),
                            TextInput::make('size_w')->label('Width (px)')->numeric()->required()->minValue(200)->maxValue(4000)->default(1080)
                                ->disabled(fn (Get $get) => $get('size_preset') !== 'custom')->dehydrated(),
                            TextInput::make('size_h')->label('Height (px)')->numeric()->required()->minValue(200)->maxValue(4000)->default(1920)
                                ->disabled(fn (Get $get) => $get('size_preset') !== 'custom')->dehydrated(),
                            Toggle::make('is_active')->label('Active (shown on the home page)')->default(true),
                            TextInput::make('sort_order')->numeric()->default(0),
                        ]),

                    Section::make('Artwork')
                        ->description('Design at the exact frame size. Render order: background → photos → overlay → text → QR.')
                        ->columns(3)
                        ->schema([
                            self::upload('background_path', 'Background')->required()
                                ->helperText('Full design with the photo areas left empty.'),
                            self::upload('overlay_path', 'Overlay (transparent PNG)')
                                ->acceptedFileTypes(['image/png'])
                                ->helperText('Optional. Drawn above member photos.'),
                            self::upload('thumbnail_path', 'Thumbnail')
                                ->helperText('Optional. Defaults to the background.'),
                        ]),

                    Section::make('Slots & text (config JSON)')
                        ->description(new HtmlString('Coordinates are in native frame pixels. See <code>docs/FRAME_CONFIG.md</code>.'))
                        ->schema([
                            CodeEditor::make('config')
                                ->hiddenLabel()
                                ->language(Language::Json)
                                ->default(json_encode(FrameFactory::sampleConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                                ->formatStateUsing(fn ($state) => is_array($state)
                                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : $state)
                                ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                ->rules(fn (Get $get) => [new ValidFrameConfig((int) $get('size_w'), (int) $get('size_h'))])
                                ->required(),
                        ]),
                ]),

                Section::make('Live preview')
                    ->description('Saved version, rendered with sample stats (incl. a Bangla name) and placeholder photos. Save to refresh.')
                    ->columnSpan(1)
                    ->schema([
                        Html::make(fn (?Frame $record) => self::preview($record)),
                    ]),
            ]);
    }

    private static function upload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk(config('filesystems.default'))
            ->directory('public/frames/uploads')
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->maxSize(15 * 1024);
    }

    public static function preview(?Frame $record): HtmlString
    {
        if (! $record?->exists) {
            return new HtmlString('<p style="font-size:13px;opacity:.7">Save the frame to see the preview.</p>');
        }

        $url = e(FrameRenderer::pageUrl($record));
        $ratio = $record->size_w.' / '.$record->size_h;

        return new HtmlString(<<<HTML
            <div style="width:100%;aspect-ratio:{$ratio};border-radius:12px;overflow:hidden;border:1px solid rgba(0,0,0,.1)">
                <iframe src="{$url}" title="Frame preview" loading="lazy" style="width:100%;height:100%;border:0"></iframe>
            </div>
            <p style="margin-top:8px;font-size:12px"><a href="{$url}" target="_blank" rel="noopener" style="text-decoration:underline">Open full size ↗</a></p>
        HTML);
    }
}
