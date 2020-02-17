<?php

namespace OptimistDigital\NovaPageManager\Models;

use OptimistDigital\NovaPageManager\NovaPageManager;

class Page extends TemplateModel
{
    protected $appends = [
        'path'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(NovaPageManager::getPagesTableName());
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($template) {
            // Is a parent template
            if ($template->parent_id === null) {
                // Find child templates
                $childTemplates = Page::where('parent_id', '=', $template->id)->get();
                if (count($childTemplates) === 0) return;

                // Set their parent to null
                $childTemplates->each(function ($template) {
                    $template->update(['parent_id' => null]);
                });
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Page::class);
    }

    public function childDraft()
    {
        return $this->hasOne(Page::class, 'draft_parent_id', 'id');
    }

    public function localeParent()
    {
        return $this->belongsTo(Page::class);
    }

    public function isDraft()
    {
        return isset($this->preview_token) ? true : false;
    }

    public function getPathAttribute()
    {
        $localeParent = $this->localeParent;
        $isLocaleChild = $localeParent !== null;
        $pathFinderPage = $isLocaleChild ? $localeParent : $this;
        if (!isset($pathFinderPage->parent)) return NovaPageManager::getPagePath($this, $this->normalizePath($this->slug));

        $parentSlugs = [];
        $parent = $pathFinderPage->parent;
        while (isset($parent)) {
            if ($isLocaleChild) {
                $localizedPage = Page::where('locale_parent_id', $parent->id)->where('locale', $this->locale)->first();
                $parentSlugs[] = $localizedPage !== null ? $localizedPage->slug : $parent->slug;
            } else {
                $parentSlugs[] = $parent->slug;
            }
            $parent = $parent->parent;
        }
        $parentSlugs = array_reverse($parentSlugs);

        $normalizedPath = $this->normalizePath(implode('/', $parentSlugs) . "/" . $this->slug);

        return NovaPageManager::getPagePath($this, $normalizedPath);
    }

    protected function normalizePath($path)
    {
        if (isset($path[0]) && $path[0] !== '/') $path = "/$path";
        if (strlen($path) > 1 && substr($path, -1) === '/') $path = substr($path, 0, -1);
        return preg_replace('/[\/]+/', '/', $path);
    }
}
