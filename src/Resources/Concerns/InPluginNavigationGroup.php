<?php

namespace SocialDept\AtpAppviewFilament\Resources\Concerns;

use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use Throwable;

/**
 * Puts a resource in the navigation group the plugin was given.
 */
trait InPluginNavigationGroup
{
    public static function getNavigationGroup(): ?string
    {
        try {
            return AtpAppviewPlugin::get()->getNavigationGroup();
        } catch (Throwable) {
            return null;
        }
    }
}
