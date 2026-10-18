<?php

namespace SocialDept\AtpAppviewFilament\Support;

use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use Throwable;

/**
 * Where the panel links records and repos, as the plugin's recordLinks()
 * says, and blobs on their repo's PDS.
 */
class Links
{
    public static function record(string $uri): string
    {
        try {
            return AtpAppviewPlugin::get()->recordUrl($uri);
        } catch (Throwable) {
            // Outside a panel with the plugin, such as a test rendering a
            // component on its own.
            return 'https://pds.ls/'.$uri;
        }
    }

    public static function repo(string $did): string
    {
        return self::record('at://'.$did);
    }

    public static function blob(string $pds, string $did, string $cid): string
    {
        return rtrim($pds, '/').'/xrpc/com.atproto.sync.getBlob?'.http_build_query(['did' => $did, 'cid' => $cid]);
    }
}
