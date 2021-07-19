<?php

use Illuminate\Support\Collection;
use OptimistDigital\NovaPageManager\Models\TemplateModel;
use OptimistDigital\NovaPageManager\NovaPageManager;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\Heading;

// ------------------------------
// nova_get_pages_structure
// ------------------------------

if (!function_exists('nova_get_pages_structure')) {
    function nova_get_pages_structure($previewToken = null)
    {
        $formatPages = function (Collection $pages) use (&$formatPages, $previewToken) {
            $data = [];
            $pages->each(function ($page) use (&$data, &$formatPages, $previewToken) {
                $localeChildren = NovaPageManager::getPageModel()::where('locale_parent_id', $page->id)->where(function ($query) use ($previewToken) {
                    $query->where('published', true);
                    if (!empty($previewToken)) $query->orWhere('preview_token', $previewToken);
                })->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
                    if (!empty($previewToken)) $query->where('preview_token', $previewToken);
                })->get();

                $_pages = collect([$page, $localeChildren])->flatten();
                $_data = [
                    'locales' => $_pages->pluck('locale'),
                    'id' => $_pages->pluck('id', 'locale'),
                    'name' => $_pages->pluck('name', 'locale'),
                    'slug' => $_pages->pluck('slug', 'locale'),
                    'path' => $_pages->pluck('path', 'locale'),
                    'template' => $page->template,
                ];

                $children = NovaPageManager::getPageModel()::where('parent_id', $page->id)->where(function ($query) use ($previewToken) {
                    $query->where('published', true);
                    if (!empty($previewToken)) $query->orWhere('preview_token', $previewToken);
                })->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
                    if (!empty($previewToken)) $query->where('preview_token', $previewToken);
                })->get();

                if ($children->count() > 0) {
                    $_data['children'] = $formatPages($children);
                }

                $data[] = $_data;
            });
            return $data;
        };

        $parentPages = NovaPageManager::getPageModel()::whereNull('parent_id')->whereNull('locale_parent_id')->where(function ($query) use ($previewToken) {
            $query->where('published', true);
            if (!empty($previewToken)) $query->orWhere('preview_token', $previewToken);
        })->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
            if (!empty($previewToken)) $query->where('preview_token', $previewToken);
        })->get();

        return $formatPages($parentPages);
    }
}

// ------------------------------
// nova_get_pages_structure
// ------------------------------

if (!function_exists('nova_get_pages_structure_flat')) {
    function nova_get_pages_structure_flat($previewToken = null)
    {
        $formatPages = function (Collection $pages) use (&$formatPages, $previewToken) {
            $data = [];
            $pages->each(function ($page) use (&$data, &$formatPages, $previewToken) {
                $localeChildren = NovaPageManager::getPageModel()::where('locale_parent_id', $page->id)->where(function ($query) use ($previewToken) {
                    $query->where('published', true);
                    if (!empty($previewToken)) $query->orWhere('preview_token', $previewToken);
                })->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
                    if (!empty($previewToken)) $query->where('preview_token', $previewToken);
                })->get();

                $_pages = collect([$page, $localeChildren])->flatten();
                $_data = [
                    'id' => $_pages->pluck('id', 'locale'),
                    'name' => $_pages->pluck('name', 'locale'),
                    'path' => $_pages->pluck('path', 'locale'),
                    'parent_id' => $_pages->pluck('parent_id', 'locale'),
                    'template' => $page->template,
                ];

                $data[] = $_data;
            });
            return $data;
        };

        $pages = NovaPageManager::getPageModel()::where(function ($query) use ($previewToken) {
            $query->where('published', true);
            if (!empty($previewToken)) $query->orWhere('preview_token', $previewToken);
        })->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
            if (!empty($previewToken)) $query->where('preview_token', $previewToken);
        })->whereNull('locale_parent_id')->get();

        return $formatPages($pages);
    }
}

// ------------------------------
// nova_get_regions
// ------------------------------

if (!function_exists('nova_get_regions')) {
    function nova_get_regions()
    {
        $formatRegions = function (Collection $regions) {
            $data = [];
            $regions->each(function ($region) use (&$data) {
                $localeChildren = NovaPageManager::getRegionModel()::where('locale_parent_id', $region->id)->get();
                $_regions = collect([$region, $localeChildren])->flatten();
                $data[] = [
                    'locales' => $_regions->pluck('locale'),
                    'id' => $_regions->pluck('id', 'locale'),
                    'name' => $_regions->pluck('name', 'locale'),
                    'template' => $region->template,
                    'data' => $_regions->map(function ($_region) {
                        return [
                            'locale' => $_region->locale,
                            'data' => nova_resolve_template_model_data($_region),
                        ];
                    })->pluck('data', 'locale'),
                ];
            });
            return $data;
        };

        $parentRegions = NovaPageManager::getRegionModel()::whereNull('locale_parent_id')->get();
        return $formatRegions($parentRegions);
    }
}


// ------------------------------
// nova_format_page
// ------------------------------

if (!function_exists('nova_format_page')) {
    function nova_format_page($page)
    {
        $template = collect(NovaPageManager::getPageTemplates())->first(function ($template) use ($page) {
            return $template::$name === $page->template;
        });

        if (!isset($template)) return null;

        $pageData = [
            'locale' => $page->locale ?: null,
            'id' => $page->id ?: null,
            'name' => $page->name ?: null,
            'slug' => $page->slug ?: null,
            'path' => $page->path ?: null,
            'parent_id' => $page->parent_id ?: null,
            'data' => nova_resolve_template_model_data($page),
            'template' => $page->template ?: null,
            'view' => $template::$view ?: null,
        ];

        if ($template::$seo) {
            $pageData['seo'] = [
                'title' => $page->seo_title,
                'description' => $page->seo_description,
                'image' => $page->seo_image,
            ];
        }

        return $pageData;
    }
}


// ------------------------------
// nova_get_page
// ------------------------------

if (!function_exists('nova_get_page')) {

    function nova_get_page($pageId, $previewToken = null)
    {
        if (empty($pageId)) return null;

        $page = NovaPageManager::getPageModel()::where(function ($query) use ($previewToken, $pageId) {
            $query->where('id', $pageId)->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
                if (!empty($previewToken)) $query->where('preview_token', $previewToken);
            });
        })->orWhere(function ($query) use ($previewToken, $pageId) {
            $query->where('draft_parent_id', $pageId);
            if (!empty($previewToken)) $query->where('preview_token', $previewToken);
        })->firstOrFail();

        if ((isset($page->preview_token) && $page->preview_token !== $previewToken) || empty($page)) {
            return null;
        }

        return nova_format_page($page);
    }
}


// ------------------------------
// nova_get_page_by_slug
// ------------------------------

if (!function_exists('nova_get_page_by_slug')) {

    function nova_get_page_by_slug($slug, $previewToken = null)
    {
        if (empty($slug)) return null;

        $page = NovaPageManager::getPageModel()::where('slug', $slug)
            ->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
                if (!empty($previewToken)) $query->where('preview_token', $previewToken);
            })
            ->orWhere(DB::raw("REPLACE(CONCAT(locale, '/', slug), '//', '/')"), $slug)
            ->orWhere(DB::raw("REPLACE(CONCAT(locale, '/', slug), '//', '/')"), $slug . '/')
            ->first();

        if (empty($page) || (isset($page->preview_token) && $page->preview_token !== $previewToken)) {
            return null;
        }

        return nova_format_page($page);
    }
}


// ------------------------------
// nova_resolve_template_field_value
// ------------------------------

if (!function_exists('nova_resolve_template_field_value')) {
    function nova_resolve_template_field_value($field, $fieldValue, $templateModel)
    {
        if ($field::hasMacro('resolveResponseValue')) {
            return $field->__call('resolveResponseValue', [$fieldValue, $templateModel]);
        } elseif (method_exists($field, 'resolveResponseValue')) {
            return $field->resolveResponseValue($fieldValue, $templateModel);
        } else {
            return $fieldValue;
        }
    }
}


// ------------------------------
// nova_resolve_template_model_data
// ------------------------------

if (!function_exists('nova_resolve_template_model_data')) {
    function nova_resolve_template_model_data(TemplateModel $templateModel)
    {
        if (!isset($templateModel) || !isset($templateModel->template) || !isset($templateModel->data)) return null;

        // Find the Template class for the model
        foreach (NovaPageManager::getTemplates() as $tmpl) {
            if ($tmpl::$name === $templateModel->template) $templateClass = $tmpl;
        }

        // Fail silently is template is no longer registered
        if (!isset($templateClass)) return null;

        // Get the template's fields
        $fields = collect((new $templateClass($templateModel))->fields(request()));

        return nova_resolve_fields_data($fields, $templateModel->data, $templateModel);
    }
}


// ------------------------------
// nova_resolve_fields_data
// ------------------------------

if (!function_exists('nova_resolve_fields_data')) {
    function nova_resolve_fields_data(Collection $fields, $data, $templateModel)
    {
        $resolvedData = [];

        $fields = $fields->map(function ($field) use ($data) {
            if ($field->component === 'nova-dependency-container') {
                try {
                    $field->resolveForDisplay($data, null);
                } catch (Throwable $e) {
                }

                // Are all satisfied?
                $allDepsSatisfied = empty(collect($field->meta['dependencies'])->firstWhere('satisfied', '!=', true));
                if ($allDepsSatisfied) return $field->meta['fields'];
            }
            return $field;
        })->flatten(1)->filter();

        foreach (((array) $data) as $fieldAttribute => $fieldValue) {

            $field = $fields->first(function ($value, $key) use ($fieldAttribute) {
                return (
                    ((isset($value->attribute) && $value->attribute === $fieldAttribute)) || // Normal or flexible field
                    ((isset($value->component)) && ($value->component === 'panel') && (nova_page_manager_sanitize_panel_name($value->name) === $fieldAttribute)) // Panel
                );
            });

            if (!$field || $field instanceof Heading) {
                continue;
            } else if ($field instanceof Panel) {
                $panelAttributeName = nova_page_manager_sanitize_panel_name($field->name);
                $resolvedData[$panelAttributeName] = nova_resolve_fields_data(collect($field->data), $data->{$panelAttributeName}, $templateModel);
            } else if (isset($field->component) && $field->component === 'nova-flexible-content') {
                $resolvedData[$fieldAttribute] = collect($fieldValue)->map(function ($fieldVal) use ($field, $templateModel) {
                    $accessProtectedProperty = function ($object, $property) {
                        $reflection = new ReflectionClass($object);
                        $_property = $reflection->getProperty($property);
                        $_property->setAccessible(true);
                        return $_property->getValue($object);
                    };

                    $flexibleLayouts = $accessProtectedProperty($field, 'layouts');

                    $layout = $flexibleLayouts->first(function ($flexibleLayout) use ($fieldVal, $accessProtectedProperty) {
                        $layoutName = $accessProtectedProperty($flexibleLayout, 'name');
                        return $fieldVal->layout === $layoutName;
                    });

                    return [
                        'layout' => $accessProtectedProperty($layout, 'name'),
                        'attributes' => nova_resolve_fields_data($accessProtectedProperty($layout, 'fields'), $fieldVal->attributes, $templateModel)
                    ];
                })->toArray();
            } else {
                $resolvedData[$field->attribute] = nova_resolve_template_field_value($field, $data->{$field->attribute}, $templateModel);
            }
        }

        return $resolvedData;
    }
}


// ------------------------------
// nova_page_manager_sanitize_panel_name
// ------------------------------

if (!function_exists('nova_page_manager_sanitize_panel_name')) {
    function nova_page_manager_sanitize_panel_name($name)
    {
        $name = \Illuminate\Support\Str::slug($name);
        $removedSpecialChars = preg_replace("/[^A-Za-z0-9 ]/", '', $name);
        $snakeCase = preg_replace("/\s+/", '_', $removedSpecialChars);
        return strtolower($snakeCase);
    }
}

// ------------------------------
// nova_page_manager_get_page_by_path
// ------------------------------

if (!function_exists('nova_page_manager_get_page_by_path')) {
    function nova_page_manager_get_page_by_path($path, $previewToken = null, $locale = null)
    {
        if (empty($path)) return null;
        $slugs = array_values(array_filter(explode('/', $path), 'strlen'));
        if (empty($slugs)) $slugs = ['/'];

        $parent = nova_get_page_by_slug($slugs[0], $previewToken);
        if (empty($parent)) return null;

        $isParent = $parent['parent_id'] == null;
        while (!$isParent) {
            $parent = nova_get_page($parent['parent_id']);
            $isParent = $parent['parent_id'] === null;
        }

        $page = null;
        foreach ($slugs as $slug) {
            $query = NovaPageManager::getPageModel()::where('slug', $slug)
                ->where(function ($query) use ($parent) {
                    $query
                        ->where('parent_id', $parent['id'])
                        ->orWhereNull('parent_id');
                })
                ->whereDoesntHave('childDraft', function ($query) use ($previewToken) {
                    if (!empty($previewToken)) $query->where('preview_token', $previewToken);
                });

            if (isset($locale)) $query->where('locale', $locale);
            $page = $query->first();
            if (empty($page)) return null;
            $parent = $page;
        }

        if ((isset($page->preview_token) && $page->preview_token !== $previewToken) || empty($page)) return null;
        if (empty($page)) return null;
        return $page;
    }
}

// ------------------------------
// nova_page_manager_get_page_by_template
// ------------------------------

if (!function_exists('nova_page_manager_get_page_by_template')) {
    function nova_page_manager_get_page_by_template($template, $locale = null, $previewToken = null)
    {
        if (empty($template)) return [];

        $pageQuery = NovaPageManager::getPageModel()::where('template', $template);
        if (!empty($locale)) $pageQuery->where('locale', $locale);
        if (!empty($previewToken)) $pageQuery->where('preview_token', $previewToken);

        $page = $pageQuery->first();
        if (!$page) return [];

        if (!empty($locale)) return nova_format_page($page);

        $localeChildren = NovaPageManager::getPageModel()::where('template', $template)->where('locale_parent_id', $page->id)->get();
        $pages = collect([$page, $localeChildren])->flatten()->map(fn ($_page) => nova_format_page($_page));

        return collect([
            'locales' => $pages->pluck('locale'),
            'id' => $pages->pluck('id', 'locale'),
            'name' => $pages->pluck('name', 'locale'),
            'slug' => $pages->pluck('slug', 'locale'),
            'path' => $pages->pluck('path', 'locale'),
            'parent_id' => $pages->pluck('parent_id', 'locale'),
            'template' => $page->template,
        ]);
    }
}
