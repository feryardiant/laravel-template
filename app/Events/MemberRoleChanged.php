<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRoleChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public Person $person,
        public User $actor,
        public Role $previousRole,
        public Role $newRole,
    ) {}
}
