<?php

namespace SocialDept\AtpAppviewFilament\Support;

use SocialDept\AtpAppview\Labels\Effect;
use SocialDept\AtpAppview\Labels\Policies;
use SocialDept\AtpAppview\Labels\Target;
use SocialDept\AtpAppview\Models\Label;

/**
 * Labels as Filament badges, colored by what they do.
 */
class LabelBadges
{
    public static function color(Effect $effect): string
    {
        return match ($effect) {
            Effect::Ban, Effect::Hide => 'danger',
            Effect::Review, Effect::Warn, Effect::Blur => 'warning',
            Effect::Badge => 'info',
            Effect::Ignore => 'gray',
        };
    }

    public static function effect(Label $label): Effect
    {
        return app(Policies::class)->effect($label->src, $label->val, Target::of($label->uri));
    }

    /**
     * Effects people may choose, labelled for a select.
     *
     * @return array<string, string>
     */
    public static function options(string $target): array
    {
        $options = [];

        foreach (Effect::cases() as $effect) {
            if ($effect->appliesTo($target)) {
                $options[$effect->value] = match ($effect) {
                    Effect::Ignore => 'Ignore',
                    Effect::Badge => 'Badge',
                    Effect::Warn => 'Warn',
                    Effect::Blur => 'Blur',
                    Effect::Hide => 'Hide',
                    Effect::Review => 'Needs review',
                    Effect::Ban => 'Ban',
                };
            }
        }

        return $options;
    }
}
