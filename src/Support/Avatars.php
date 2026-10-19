<?php

namespace SocialDept\AtpAppviewFilament\Support;

use Closure;
use SocialDept\AtpAppview\Profiles\Profiles;
use Throwable;

/**
 * Avatars for a page of accounts, looked up together through Profiles the
 * first time a row asks, so a page costs one lookup however many rows it
 * has. Kept for the request.
 */
class Avatars
{
    /** @var array<string, string|null> */
    protected array $urls = [];

    /**
     * @param  Closure(): array<int, string>  $page  The DIDs on the same page, fetched with this one
     */
    public function of(string $did, Closure $page): ?string
    {
        if (! array_key_exists($did, $this->urls)) {
            $dids = array_values(array_unique([$did, ...$page()]));

            try {
                $profiles = app(Profiles::class)->basic($dids);
            } catch (Throwable) {
                $profiles = [];
            }

            foreach ($dids as $each) {
                $this->urls[$each] = $profiles[$each]['avatar'] ?? null;
            }
        }

        return $this->urls[$did];
    }

    /**
     * A circle with the handle's first letter, for accounts without an avatar.
     */
    public static function placeholder(?string $handle): string
    {
        $letter = htmlspecialchars(mb_strtoupper(mb_substr($handle ?? '?', 0, 1)), ENT_XML1);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#a1a1aa"/>'
            .'<text x="16" y="21" font-family="ui-sans-serif,system-ui,sans-serif" font-size="14" font-weight="600" fill="#fff" text-anchor="middle">'.$letter.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
