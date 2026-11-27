<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Livewire\Contacts\Form as ContactForm;
use App\Livewire\Contacts\Partials\DeleteContactModal;
use App\Livewire\Members\Form as MemberForm;
use App\Livewire\Members\Partials\RemoveMemberModal;
use App\Livewire\Notifications\Index as NotificationIndex;
use App\Livewire\Notifications\Partials\NotificationModal;
use App\Livewire\OrgSettings\Features;
use App\Livewire\OrgSettings\General;
use App\Livewire\OrgSettings\General\TransferOwnership;
use App\Livewire\Settings\Organizations\Create as CreateOrganizationForm;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Create/update/delete flow query/timing measurements.
 *
 * Mutates data; run against a disposable clone.
 * Run: QUERY_WRITE=1 DB_DATABASE=query_baseline_writes vendor/bin/pest tests/Stress --group=query-write
 */

beforeEach(function (): void {
    if (getenv('QUERY_WRITE') !== '1') {
        $this->markTestSkipped('Set QUERY_WRITE=1 and DB_DATABASE to a disposable database.');
    }

    if (getenv('DB_DATABASE') === false) {
        $this->markTestSkipped('Set DB_DATABASE to the database under measurement.');
    }

    expect(DB::connection()->getDatabaseName())->toBe(getenv('DB_DATABASE'));
});

$GLOBALS['write_queries'] = [];

function writeReset(): void
{
    $GLOBALS['write_queries'] = [];
}

function writeMeasure(string $label, Closure $callback): void
{
    writeReset();
    $start = hrtime(true);
    $callback();
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    $captured = $GLOBALS['write_queries'];

    fwrite(STDERR, \sprintf("### %-42s %8.1f ms %5d q\n", $label, $elapsed, \count($captured)));

    $counts = [];

    foreach ($captured as $sql) {
        $key = preg_replace('/\b\d+\b/', '?', preg_replace("/'[^']*'/", '?', $sql) ?? $sql) ?? $sql;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    arsort($counts);

    foreach (\array_slice($counts, 0, 6, true) as $sql => $count) {
        if ($count < 2) {
            continue;
        }

        fwrite(STDERR, \sprintf("    %2dx %s\n", $count, mb_substr($sql, 0, 140)));
    }
}

test('create, update, and delete flow baseline', function () {
    DB::listen(function (QueryExecuted $query): void {
        $GLOBALS['write_queries'][] = $query->toRawSql();
    });

    $benchOwner = User::query()->where('handle', 'benchowner')->firstOrFail();
    $rootOwner = User::query()->where('handle', 'rootowner')->firstOrFail();
    $bench = Organization::query()->findOrFail(2);
    $root = Organization::query()->findOrFail(1);
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

    $switchTo = function (User $user, Organization $organization): void {
        test()->actingAs($user);
        session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);
        app(CurrentOrganization::class)->forget();
    };

    $switchTo($benchOwner, $bench);

    $individualName = "Baseline Individual {$suffix}";
    writeMeasure('contact create (individual)', fn () => Livewire::test(ContactForm::class)
        ->call('openCreate')
        ->set('type', 'individual')
        ->set('name', $individualName)
        ->set('role', OrganizationRelationshipType::Customer->value)
        ->set('startDate', now()->toDateString())
        ->set('contactEmail', "individual-{$suffix}@example.test")
        ->set('contactPhone', '081234567890')
        ->call('save'));

    expect(Person::query()->where('name', $individualName)->exists())->toBeTrue();

    $organizationName = "Baseline Organization {$suffix}";
    writeMeasure('contact create (organization)', fn () => Livewire::test(ContactForm::class)
        ->call('openCreate')
        ->set('type', 'organization')
        ->set('name', $organizationName)
        ->set('contactName', "Baseline Contact {$suffix}")
        ->set('role', OrganizationRelationshipType::Customer->value)
        ->set('startDate', now()->toDateString())
        ->set('contactEmail', "org-{$suffix}@example.test")
        ->set('contactPhone', '081234567891')
        ->set('contactPersonEmail', "contact-{$suffix}@example.test")
        ->set('contactPersonPhone', '081234567892')
        ->call('save'));

    expect(Organization::query()->where('name', $organizationName)->exists())->toBeTrue();

    $editable = OrganizationRelationship::query()
        ->where('organization_id', 2)
        ->where('stakeholder_type', Organization::class)
        ->whereHas('stakeholder', fn ($query) => $query->where('name', 'Org 2-1'))
        ->firstOrFail();

    $updatedName = "Org 2-1 Updated {$suffix}";
    writeMeasure('contact update', fn () => Livewire::test(ContactForm::class)
        ->call('openEdit', $editable->getKey())
        ->set('name', $updatedName)
        ->call('save'));

    expect(Organization::query()->where('name', $updatedName)->exists())->toBeTrue();

    $deletable = OrganizationRelationship::query()
        ->where('organization_id', 2)
        ->where('stakeholder_type', Organization::class)
        ->whereHas('stakeholder', fn ($query) => $query->where('name', 'Org 2-2'))
        ->firstOrFail();

    writeMeasure('contact delete', fn () => Livewire::test(DeleteContactModal::class)
        ->call('confirm', $deletable->getKey())
        ->call('delete'));

    expect(OrganizationRelationship::query()->whereKey($deletable->getKey())->exists())->toBeFalse();

    $memberName = "Baseline Member {$suffix}";
    writeMeasure('member create (credentialed)', fn () => Livewire::test(MemberForm::class)
        ->call('openCreate')
        ->set('name', $memberName)
        ->set('email', "member-{$suffix}@example.test")
        ->set('role', Role::Member->value)
        ->set('createCredential', true)
        ->set('contactEmail', "member-contact-{$suffix}@example.test")
        ->set('contactPhone', '081234567893')
        ->call('create'));

    expect(Person::query()->where('name', $memberName)->exists())->toBeTrue();

    $plainMemberName = "Baseline Plain Member {$suffix}";
    writeMeasure('member create (no credential)', fn () => Livewire::test(MemberForm::class)
        ->call('openCreate')
        ->set('name', $plainMemberName)
        ->set('email', "plain-{$suffix}@example.test")
        ->set('role', Role::Member->value)
        ->set('createCredential', false)
        ->call('create'));

    expect(Person::query()->where('name', $plainMemberName)->exists())->toBeTrue();

    $member = Person::query()->where('name', $plainMemberName)->firstOrFail();
    $newRole = $member->membershipIn($bench)?->role === Role::Member ? Role::Admin : Role::Member;

    writeMeasure('member update (role)', fn () => Livewire::test(MemberForm::class, ['person' => $member])
        ->set('role', $newRole->value)
        ->call('update'));

    expect($member->fresh()->membershipIn($bench)?->role)->toBe($newRole);

    $removeTarget = Person::query()->where('name', $memberName)->firstOrFail();
    writeMeasure('member remove', fn () => Livewire::test(RemoveMemberModal::class)
        ->call('confirm', $removeTarget->getKey())
        ->call('delete'));

    expect($removeTarget->fresh()->membershipIn($bench))->toBeNull();

    writeMeasure('organization update', fn () => Livewire::test(General::class)
        ->set('name', "Benchmark Tenant {$suffix}")
        ->set('address', [
            'line' => '', 'rt' => '', 'rw' => '', 'postal_code' => '',
            'province_code' => '', 'regency_code' => '', 'district_code' => '', 'village_code' => '',
        ])
        ->call('update'));

    expect($bench->fresh()->name)->toBe("Benchmark Tenant {$suffix}");

    writeMeasure('feature toggle (contact gender)', fn () => Livewire::test(Features::class)
        ->set('features.contact-gender', true));

    expect(Feature::ContactGender->enabled($bench))->toBeTrue();

    $ownOrgName = "Baseline Own Org {$suffix}";

    writeMeasure('organization create (own)', fn () => Livewire::test(CreateOrganizationForm::class)
        ->call('open')
        ->set('name', $ownOrgName)
        ->set('parentId', (string) $bench->getKey())
        ->set('kind', 'subsidiary')
        ->call('create'));

    expect(Organization::query()->where('name', $ownOrgName)->exists())->toBeTrue();

    $switchTo($benchOwner, $bench);

    $profileSummary = "Bench summary {$suffix}";

    writeMeasure('profile update', fn () => Livewire::test(Profile::class)
        ->set('summary', $profileSummary)
        ->call('updateProfileInformation'));

    expect(Person::query()->where('user_id', $benchOwner->getKey())->value('summary'))->toBe($profileSummary);

    writeMeasure('preferences update', fn () => test()->patch(route('preferences.update'), [
        'locale' => 'en',
        'timezone' => 'Asia/Jakarta',
    ]));

    expect($benchOwner->fresh()->timezone)->toBe('Asia/Jakarta');

    $newPassword = 'new-password-'.$suffix;

    writeMeasure('password update', fn () => Livewire::test(Security::class)
        ->set('currentPassword', 'password')
        ->set('password', $newPassword)
        ->set('passwordConfirmation', $newPassword)
        ->call('updatePassword'));

    expect(Hash::check($newPassword, $benchOwner->fresh()->password))->toBeTrue();

    $notificationId = $benchOwner->notifications()->whereNull('read_at')->value('id');

    writeMeasure('notification mark read', function () use ($notificationId): void {
        Livewire::test(NotificationIndex::class)
            ->set('selectedNotifications', [$notificationId])
            ->call('markSelectedAsRead');
    });

    expect($benchOwner->notifications()->whereKey($notificationId)->value('read_at'))->not->toBeNull();

    $modalNotificationId = $benchOwner->notifications()->whereNull('read_at')->value('id');

    writeMeasure('notification open (mark one)', fn () => Livewire::test(NotificationModal::class)->call('open', $modalNotificationId));

    expect($benchOwner->notifications()->whereKey($modalNotificationId)->value('read_at'))->not->toBeNull();

    $switchTo($rootOwner, $root);

    $subsidiaryName = "Baseline Subsidiary {$suffix}";

    writeMeasure('subsidiary create', fn () => Livewire::test(CreateOrganizationForm::class, ['internal' => true])
        ->call('open')
        ->set('name', $subsidiaryName)
        ->set('kind', 'subsidiary')
        ->call('create'));

    expect(Organization::query()->where('name', $subsidiaryName)->exists())->toBeTrue();

    $recipient = $root->members()
        ->whereKeyNot($rootOwner->person->getKey())
        ->whereHas('user')
        ->firstOrFail();

    writeMeasure('ownership transfer request', fn () => Livewire::test(TransferOwnership::class)
        ->set('recipientId', (string) $recipient->getKey())
        ->set('confirmation', $root->name)
        ->call('transfer'));

    expect(ApprovalRequest::query()
        ->where('organization_id', $root->getKey())
        ->where('status', 'pending')
        ->exists())->toBeTrue();

    writeMeasure('ownership transfer cancel', fn () => Livewire::test(TransferOwnership::class)
        ->call('cancelTransfer'));

    expect(ApprovalRequest::query()
        ->where('organization_id', $root->getKey())
        ->where('status', 'pending')
        ->exists())->toBeFalse();
})->group('query-write');
