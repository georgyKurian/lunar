<?php

namespace Lunar\Admin\Support\Forms\Components;

use Closure;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component as Livewire;
use Lunar\Admin\Support\Facades\AttributeData;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;

class Attributes extends Forms\Components\Group
{
    public static function make(Closure|array $schema = []): static
    {
        return app(
            static::class,
            [
                'schema' => ! blank($schema) ? $schema : function (\Filament\Forms\Get $get, Livewire $livewire, ?Model $record) {
                    $modelClass = $livewire::getResource()::getModel();

                    $productTypeId = null;

                    $morphMap = (new $modelClass)->getMorphClass();

                    $attributeQuery = Attribute::where('attribute_type', $modelClass);

                    // Products are unique in that they use product types to map attributes, so we need
                    // to try and find the product type ID
                    if ($morphMap == (new Product)->getMorphClass()) {
                        $productTypeId = $record?->product_type_id ?: ProductType::first()->id;

                        // If we have a product type, the attributes should be based off that.
                        if ($productTypeId) {
                            $attributeQuery = ProductType::find($productTypeId)->productAttributes();
                        }
                    }

                    if ($morphMap == (new ProductVariant)->getMorphClass()) {
                        $productTypeId = $record->product?->product_type_id ?: ProductType::first()->id;

                        // If we have a product type, the attributes should be based off that.
                        if ($productTypeId) {
                            $attributeQuery = ProductType::find($productTypeId)->variantAttributes();
                        }
                    }

                    $attributes = $attributeQuery->orderBy('position')->get();

                    $groups = AttributeGroup::where(
                        'attributable_type',
                        $morphMap
                    )->orderBy('position', 'asc')
                        ->get()
                        ->map(function ($group) use ($attributes) {
                            return [
                                'model' => $group,
                                'fields' => $attributes->groupBy('attribute_group_id')->get($group->id, []),
                            ];
                        });

                    $groupComponents = [];

                    foreach ($groups as $group) {
                        $sectionFields = [];

                        foreach ($group['fields'] as $field) {
                            $sectionFields[] = AttributeData::getFilamentComponent($field);
                        }

                        $groupComponents[] = Forms\Components\Section::make($group['model']->translate('name'))
                            ->schema($sectionFields);
                    }

                    return $groupComponents;
                },
            ]
        )
            ->configure()
            ->key('attributeData')
            ->mutateStateForValidationUsing(function ($state) {
                if (! is_array($state)) {
                    return $state;
                }

                foreach ($state as $key => $value) {
                    if (! $value instanceof \Lunar\Base\Fieldtype) {
                        continue;
                    }

                    $state[$key] = $value->getValue();
                }

                return $state;
            });
    }
}