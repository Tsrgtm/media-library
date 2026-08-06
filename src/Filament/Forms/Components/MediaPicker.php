<?php

namespace Tsrgtm\MediaLibrary\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Tsrgtm\MediaLibrary\Concerns\HasMedia;
use Tsrgtm\MediaLibrary\Models\Media;

class MediaPicker extends Field
{
    protected string $view =
        'media-library::filament.forms.components.media-picker';

    protected string|Closure $collection = 'default';

    protected bool|Closure $multiple = false;

    protected int|Closure|null $minItems = null;

    protected int|Closure|null $maxItems = null;

    protected array|Closure $acceptedKinds = [];

    protected array|Closure $acceptedExtensions = [];

    protected array|Closure $acceptedMimeTypes = [];

    protected bool|Closure $uploadable = true;

    protected bool|Closure $searchable = true;

    protected bool|Closure $reorderable = true;

    protected bool|Closure $removable = true;

    protected bool|Closure $replaceable = true;

    protected bool|Closure $showFolders = true;

    protected bool|Closure $showFileDetails = true;

    protected string|Closure $pickerHeading = 'Choose media';

    protected string|Closure $emptyLabel = 'No media selected';

    protected string|Closure $selectButtonLabel = 'Choose media';

    protected string|Closure|null $defaultFolder = null;

    protected array|Closure $customProperties = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->default(fn (): array|string|null => $this->isMultiple() ? [] : null)
            ->dehydrated(false)
            ->afterStateHydrated(
                function (
                    MediaPicker $component,
                    mixed $state,
                    ?Model $record,
                ): void {
                    if (
                        ! $record
                        || ! $record->exists
                        || ! $component->recordUsesMedia($record)
                    ) {
                        $component->state(
                            $component->normalizeState($state),
                        );

                        return;
                    }

                    $ids = $record
                        ->getMedia($component->getCollection())
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    $component->state(
                        $component->isMultiple()
                            ? $ids
                            : ($ids[0] ?? null),
                    );
                },
            )
            ->saveRelationshipsUsing(
                function (
                    MediaPicker $component,
                    Model $record,
                    mixed $state,
                ): void {
                    if (! $component->recordUsesMedia($record)) {
                        throw ValidationException::withMessages([
                            $component->getStatePath() => 'The model must use the '
                                .HasMedia::class
                                .' trait before MediaPicker can save.',
                        ]);
                    }

                    $ids = $component->validatedMediaIds($state);
                    $collection = $component->getCollection();

                    if ($component->isMultiple()) {
                        $record->clearMediaCollection($collection);

                        foreach ($ids as $index => $mediaId) {
                            $record->attachMedia(
                                media: $mediaId,
                                collection: $collection,
                                customProperties: $component->getCustomProperties(),
                                sortOrder: $index,
                            );
                        }

                        return;
                    }

                    if ($ids === []) {
                        $record->clearMediaCollection($collection);

                        return;
                    }

                    $record->replaceMedia(
                        media: $ids[0],
                        collection: $collection,
                        customProperties: $component->getCustomProperties(),
                    );
                },
            );
    }

    public function collection(
        string|Closure $collection,
    ): static {
        $this->collection = $collection;

        return $this;
    }

    public function multiple(
        bool|Closure $condition = true,
    ): static {
        $this->multiple = $condition;

        return $this;
    }

    public function minItems(
        int|Closure|null $count,
    ): static {
        $this->minItems = $count;

        return $this;
    }

    public function maxItems(
        int|Closure|null $count,
    ): static {
        $this->maxItems = $count;

        return $this;
    }

    public function acceptedKinds(
        array|string|Closure $kinds,
    ): static {
        $this->acceptedKinds = is_string($kinds)
            ? [$kinds]
            : $kinds;

        return $this;
    }

    public function images(): static
    {
        return $this->acceptedKinds(['image']);
    }

    public function videos(): static
    {
        return $this->acceptedKinds(['video']);
    }

    public function audio(): static
    {
        return $this->acceptedKinds(['audio']);
    }

    public function documents(): static
    {
        return $this->acceptedKinds(['document']);
    }

    public function archives(): static
    {
        return $this->acceptedKinds(['archive']);
    }

    public function acceptedExtensions(
        array|string|Closure $extensions,
    ): static {
        $this->acceptedExtensions = is_string($extensions)
            ? [$extensions]
            : $extensions;

        return $this;
    }

    public function acceptedMimeTypes(
        array|string|Closure $mimeTypes,
    ): static {
        $this->acceptedMimeTypes = is_string($mimeTypes)
            ? [$mimeTypes]
            : $mimeTypes;

        return $this;
    }

    public function uploadable(
        bool|Closure $condition = true,
    ): static {
        $this->uploadable = $condition;

        return $this;
    }

    public function searchable(
        bool|Closure $condition = true,
    ): static {
        $this->searchable = $condition;

        return $this;
    }

    public function reorderable(
        bool|Closure $condition = true,
    ): static {
        $this->reorderable = $condition;

        return $this;
    }

    public function removable(
        bool|Closure $condition = true,
    ): static {
        $this->removable = $condition;

        return $this;
    }

    public function replaceable(
        bool|Closure $condition = true,
    ): static {
        $this->replaceable = $condition;

        return $this;
    }

    public function showFolders(
        bool|Closure $condition = true,
    ): static {
        $this->showFolders = $condition;

        return $this;
    }

    public function showFileDetails(
        bool|Closure $condition = true,
    ): static {
        $this->showFileDetails = $condition;

        return $this;
    }

    public function pickerHeading(
        string|Closure $heading,
    ): static {
        $this->pickerHeading = $heading;

        return $this;
    }

    public function emptyLabel(
        string|Closure $label,
    ): static {
        $this->emptyLabel = $label;

        return $this;
    }

    public function selectButtonLabel(
        string|Closure $label,
    ): static {
        $this->selectButtonLabel = $label;

        return $this;
    }

    public function defaultFolder(
        string|Closure|null $slug,
    ): static {
        $this->defaultFolder = $slug;

        return $this;
    }

    public function customProperties(
        array|Closure $properties,
    ): static {
        $this->customProperties = $properties;

        return $this;
    }

    public function getCollection(): string
    {
        return (string) $this->evaluate($this->collection);
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    public function getMinItems(): ?int
    {
        $value = $this->evaluate($this->minItems);

        return filled($value) ? max(0, (int) $value) : null;
    }

    public function getMaxItems(): ?int
    {
        $value = $this->evaluate($this->maxItems);

        if (! filled($value)) {
            return $this->isMultiple() ? null : 1;
        }

        return max(1, (int) $value);
    }

    public function getAcceptedKinds(): array
    {
        return $this->normalizeStrings(
            Arr::wrap($this->evaluate($this->acceptedKinds)),
        );
    }

    public function getAcceptedExtensions(): array
    {
        return collect(
            Arr::wrap(
                $this->evaluate($this->acceptedExtensions),
            ),
        )
            ->map(
                fn ($extension): string => strtolower(ltrim((string) $extension, '.')),
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getAcceptedMimeTypes(): array
    {
        return $this->normalizeStrings(
            Arr::wrap(
                $this->evaluate($this->acceptedMimeTypes),
            ),
        );
    }

    public function isUploadable(): bool
    {
        return (bool) $this->evaluate($this->uploadable);
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->searchable);
    }

    public function isReorderable(): bool
    {
        return $this->isMultiple()
            && (bool) $this->evaluate($this->reorderable);
    }

    public function isRemovable(): bool
    {
        return (bool) $this->evaluate($this->removable);
    }

    public function isReplaceable(): bool
    {
        return (bool) $this->evaluate($this->replaceable);
    }

    public function shouldShowFolders(): bool
    {
        return (bool) $this->evaluate($this->showFolders);
    }

    public function shouldShowFileDetails(): bool
    {
        return (bool) $this->evaluate($this->showFileDetails);
    }

    public function getPickerHeading(): string
    {
        return (string) $this->evaluate($this->pickerHeading);
    }

    public function getEmptyLabel(): string
    {
        return (string) $this->evaluate($this->emptyLabel);
    }

    public function getSelectButtonLabel(): string
    {
        return (string) $this->evaluate(
            $this->selectButtonLabel,
        );
    }

    public function getDefaultFolder(): ?string
    {
        $value = $this->evaluate($this->defaultFolder);

        return filled($value) ? (string) $value : null;
    }

    public function getCustomProperties(): array
    {
        return (array) $this->evaluate(
            $this->customProperties,
        );
    }

    public function getInputAccept(): string
    {
        $values = [
            ...$this->getAcceptedMimeTypes(),
            ...array_map(
                fn (string $extension): string => ".{$extension}",
                $this->getAcceptedExtensions(),
            ),
        ];

        if ($values === []) {
            foreach ($this->getAcceptedKinds() as $kind) {
                $values[] = match ($kind) {
                    'image' => 'image/*',
                    'video' => 'video/*',
                    'audio' => 'audio/*',
                    default => '',
                };
            }
        }

        return collect($values)
            ->filter()
            ->unique()
            ->implode(',');
    }

    public function normalizeState(
        mixed $state,
    ): array|int|null {
        $ids = collect(Arr::wrap($state))
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($this->isMultiple()) {
            $maximum = $this->getMaxItems();

            return $maximum
                ? $ids->take($maximum)->all()
                : $ids->all();
        }

        return $ids->first();
    }

    protected function validatedMediaIds(
        mixed $state,
    ): array {
        $normalized = $this->normalizeState($state);

        $ids = $this->isMultiple()
            ? (array) $normalized
            : array_values(array_filter([$normalized]));

        $minimum = $this->getMinItems();
        $maximum = $this->getMaxItems();
        $count = count($ids);

        if ($minimum !== null && $count < $minimum) {
            throw ValidationException::withMessages([
                $this->getStatePath() => "Select at least {$minimum} media item(s).",
            ]);
        }

        if ($maximum !== null && $count > $maximum) {
            throw ValidationException::withMessages([
                $this->getStatePath() => "Select no more than {$maximum} media item(s).",
            ]);
        }

        if ($ids === []) {
            return [];
        }

        $query = Media::query()
            ->whereKey($ids)
            ->whereNull('deleted_at')
            ->where('status', 'ready');

        $kinds = $this->getAcceptedKinds();
        $extensions = $this->getAcceptedExtensions();
        $mimeTypes = $this->getAcceptedMimeTypes();

        if ($kinds !== []) {
            $query->whereIn('kind', $kinds);
        }

        if ($extensions !== []) {
            $query->whereIn('extension', $extensions);
        }

        if ($mimeTypes !== []) {
            $query->where(function ($query) use ($mimeTypes): void {
                foreach ($mimeTypes as $index => $mimeType) {
                    $method = $index === 0 ? 'where' : 'orWhere';

                    if (str_ends_with($mimeType, '/*')) {
                        $query->{$method}(
                            'mime_type',
                            'like',
                            substr($mimeType, 0, -1).'%',
                        );
                    } else {
                        $query->{$method}(
                            'mime_type',
                            $mimeType,
                        );
                    }
                }
            });
        }

        $availableIds = $query
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($availableIds) !== count($ids)) {
            throw ValidationException::withMessages([
                $this->getStatePath() => 'One or more selected media items are unavailable '
                    .'or do not match this field.',
            ]);
        }

        $order = array_flip($ids);

        usort(
            $availableIds,
            fn (int $a, int $b): int => $order[$a] <=> $order[$b],
        );

        return $availableIds;
    }

    protected function recordUsesMedia(Model $record): bool
    {
        return in_array(
            HasMedia::class,
            class_uses_recursive($record),
            true,
        );
    }

    protected function normalizeStrings(
        array $values,
    ): array {
        return collect($values)
            ->map(fn ($value): string => strtolower(
                trim((string) $value),
            ))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
