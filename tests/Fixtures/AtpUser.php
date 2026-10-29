<?php

namespace SocialDept\AtpAppviewFilament\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;
use SocialDept\AtpClient\Contracts\HasAtpSession;

class AtpUser extends User implements HasAtpSession
{
    public function getAtpDid(): ?string
    {
        return $this->getAttribute('did');
    }
}
