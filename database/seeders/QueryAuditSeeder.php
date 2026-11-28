<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Seeds a multi-tenant dataset for query measurements.
 *
 * Manual only, on an empty database:
 *
 *   php artisan db:seed --class=QueryAuditSeeder
 *
 * Scale with AUDIT_TENANTS (default 1000). The first two tenants are fixed
 * (root and benchmark) so the same flows can be measured as the global data
 * grows. This is a measurement fixture, not demo data: it inserts in bulk and
 * bypasses model events.
 *
 * The fixture rows commit in one transaction; `setval` and `ANALYZE` run after
 * it. If they fail, the data is already committed and the empty-database guard
 * blocks a rerun, so run both by hand or recreate the disposable database.
 */
class QueryAuditSeeder extends Seeder
{
    private const SHARED_PER_TYPE = 40;

    private const BENCH_MEMBERS = 50;

    private const BENCH_CONTACTS_PER_TYPE = 100;

    private const SEED = 20261127;

    /** @var list<OrganizationRelationshipType> */
    private const EXTERNAL_TYPES = [
        OrganizationRelationshipType::Customer,
        OrganizationRelationshipType::Supplier,
        OrganizationRelationshipType::Vendor,
    ];

    private string $password;

    private CarbonImmutable $today;

    private CarbonImmutable $benchToday;

    private CarbonImmutable $benchWeekStart;

    private int $nextUserId = 3;

    private int $nextPersonId = 3;

    private int $nextOrgId = 1;

    private int $nextRelationshipId = 1;

    /** @var array<int, int> */
    private array $ownerPersonIds = [];

    /** @var array<int, list<int>> */
    private array $memberPersonIds = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $buffer = [
        'people' => [],
        'organizations' => [],
        'contact_details' => [],
        'addresses' => [],
        'organization_relationships' => [],
        'organization_relationship_periods' => [],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->abort('Refusing to seed production.');

            return;
        }

        if (DB::table('organizations')->exists()) {
            $this->abort('The database already has organizations. Run this seeder on an empty database.');

            return;
        }

        $started = hrtime(true);

        mt_srand(self::SEED);

        $this->today = CarbonImmutable::today();
        $this->benchToday = CarbonImmutable::now('Asia/Jakarta');
        $this->benchWeekStart = $this->benchToday->startOfWeek(CarbonImmutable::SUNDAY);
        $this->password = Hash::make('password');

        $configuredTenants = getenv('AUDIT_TENANTS');
        $tenantCount = max(10, \is_string($configuredTenants) ? (int) $configuredTenants : 1000);

        $tenants = $this->planTenants($tenantCount);

        DB::transaction(function () use ($tenants, $tenantCount): void {
            $this->seedKnownUsers();
            $this->seedTenants($tenants);

            $this->nextPersonId = $this->nextUserId;
            $this->nextOrgId = $tenantCount + 1;

            $this->seedSubsidiaries($tenants);
            $this->seedBenchMemberships($tenants);
            $this->seedSharedContacts($tenants);
            $this->seedUniqueContacts($tenants);
            $this->seedNotifications();
            $this->seedAuditLogs($tenants);
            $this->seedApprovals($tenants);

            $this->flush();
        });

        try {
            $this->resetSequences();
            $this->analyze();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Fixture committed, but setval/ANALYZE failed. Run them by hand or recreate the disposable database.',
                previous: $exception,
            );
        }

        $elapsed = (hrtime(true) - $started) / 1_000_000_000;

        $this->report($tenantCount, $elapsed);
    }

    /**
     * Build the tenant plan and the subsidiary tree.
     *
     * @return list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>
     */
    private function planTenants(int $count): array
    {
        $tenants = [];

        for ($index = 0; $index < $count; $index++) {
            $isRoot = $index === 0;
            $isBench = $index === 1;
            $parent = null;
            $depth = 0;

            if ($index > 0) {
                $parent = 0;
                $depth = 1;

                if ($index > 2 && mt_rand(1, 100) <= 30) {
                    $candidate = mt_rand(1, $index - 1);

                    if ($tenants[$candidate]['depth'] < 3) {
                        $parent = $candidate;
                        $depth = $tenants[$candidate]['depth'] + 1;
                    }
                }
            }

            $tenants[] = [
                'id' => $index + 1,
                'name' => $isRoot
                    ? 'Root Tenant'
                    : ($isBench ? 'Benchmark Tenant' : \sprintf('Tenant %04d', $index + 1)),
                'isRoot' => $isRoot,
                'isBench' => $isBench,
                'members' => $isRoot ? 5 : ($isBench ? self::BENCH_MEMBERS : mt_rand(20, 50)),
                'orgContacts' => $isBench ? self::BENCH_CONTACTS_PER_TYPE : mt_rand(50, 100),
                'individualContacts' => $isBench ? self::BENCH_CONTACTS_PER_TYPE : mt_rand(50, 100),
                'parent' => $parent,
                'depth' => $depth,
            ];
        }

        return $tenants;
    }

    /**
     * Create the two known logins so the benchmark flows can be opened.
     */
    private function seedKnownUsers(): void
    {
        $created = $this->today->subYears(3);

        $this->insertRows('users', [
            $this->userRow(1, 'rootowner', 'rootowner@example.test', $created),
            $this->userRow(2, 'benchowner', 'benchowner@example.test', $created),
        ]);

        $this->insertRows('people', [
            $this->personRow(1, 1, null, 'Root Owner', $created),
            $this->personRow(2, 2, null, 'Bench Owner', $created),
        ]);
    }

    /**
     * Create every tenant with its members and memberships.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedTenants(array $tenants): void
    {
        foreach ($tenants as $tenant) {
            $members = $this->memberPlan($tenant);

            $userRows = [];
            $personRows = [];

            foreach ($members as $member) {
                if ($member['userId'] === 1 || $member['userId'] === 2) {
                    continue;
                }

                $created = $member['start']->subDays(mt_rand(0, 30));

                $userRows[] = $this->userRow(
                    $member['userId'],
                    \sprintf('m%d-%d', $tenant['id'], $member['index']),
                    \sprintf('m%d-%d@example.test', $tenant['id'], $member['index']),
                    $created,
                );

                $personRows[] = $this->personRow($member['userId'], $member['userId'], null, $member['name'], $created);
            }

            $this->insertRows('users', $userRows);

            foreach ($personRows as $personRow) {
                $this->add('people', $personRow);
            }

            // Member people reference the tenant, so insert them with a null
            // owner first and backfill once the tenant row exists.
            $this->flush();

            $ownerUserId = $members[0]['userId'];
            $tenantCreatedAt = $this->tenantCreatedAt($tenant);

            DB::table('organizations')->insert([
                'id' => $tenant['id'],
                'uuid' => (string) Str::uuid(),
                'contact_id' => $ownerUserId,
                'owner_organization_id' => $tenant['id'],
                'root_organization_id' => $tenant['isRoot'] ? null : 1,
                'is_tenant' => true,
                'name' => $tenant['name'],
                'summary' => null,
                'logo_url' => null,
                'created_at' => $tenantCreatedAt,
                'updated_at' => $tenantCreatedAt,
                'deleted_at' => null,
            ]);

            DB::table('people')
                ->whereIn('id', array_column($members, 'userId'))
                ->update(['owner_organization_id' => $tenant['id']]);

            $this->add('contact_details', $this->detailRow(Organization::class, $tenant['id'], 'email', \sprintf('tenant%d@example.test', $tenant['id'])));
            $this->add('contact_details', $this->detailRow(Organization::class, $tenant['id'], 'phone', \sprintf('0812%06d', $tenant['id'])));

            if ($tenant['isRoot'] || $tenant['isBench'] || mt_rand(1, 100) <= 30) {
                $this->add('addresses', $this->addressRow(Organization::class, $tenant['id']));
            }

            foreach ($members as $member) {
                $relationshipId = $this->nextRelationshipId++;

                $this->add('organization_relationships', [
                    'id' => $relationshipId,
                    'organization_id' => $tenant['id'],
                    'stakeholder_type' => Person::class,
                    'stakeholder_id' => $member['userId'],
                    'code' => null,
                    'type' => OrganizationRelationshipType::Member->value,
                    'kind' => null,
                    'role' => (string) $member['role']->value,
                    'is_primary' => $member['primary'] ? true : null,
                    'remark' => null,
                    'start_date' => $member['start']->toDateString(),
                    'finish_date' => $member['finish']?->toDateString(),
                ]);

                $this->add('organization_relationship_periods', $this->periodRow(
                    $tenant['id'],
                    $relationshipId,
                    Person::class,
                    $member['userId'],
                    OrganizationRelationshipType::Member,
                    $member['start'],
                    $member['finish'],
                    $member['role'],
                ));
            }

            $this->ownerPersonIds[$tenant['id']] = $ownerUserId;
            $this->memberPersonIds[$tenant['id']] = array_column($members, 'userId');

            $this->flush();
        }
    }

    /**
     * Build the member list for a tenant.
     *
     * @param  array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}  $tenant
     * @return list<array{index: int, userId: int, name: string, role: Role, primary: bool, start: CarbonImmutable, finish: ?CarbonImmutable}>
     */
    private function memberPlan(array $tenant): array
    {
        $members = [];

        $ownerUserId = match (true) {
            $tenant['isRoot'] => 1,
            $tenant['isBench'] => 2,
            default => $this->nextUserId++,
        };

        $ownerStart = $tenant['isBench']
            ? $this->benchToday->subYears(2)
            : $this->today->subDays(mt_rand(30, 1825));

        $members[] = [
            'index' => 1,
            'userId' => $ownerUserId,
            'name' => $tenant['isRoot'] ? 'Root Owner' : ($tenant['isBench'] ? 'Bench Owner' : \sprintf('Owner %d', $tenant['id'])),
            'role' => Role::Owner,
            'primary' => true,
            'start' => $ownerStart,
            'finish' => null,
        ];

        for ($index = 2; $index <= $tenant['members']; $index++) {
            $role = ($tenant['isBench'] && $index === 2)
                ? Role::Admin
                : (mt_rand(1, 100) <= 10 ? Role::Admin : Role::Member);

            $start = $tenant['isBench'] && $index <= 6
                ? $this->benchWeekStart->addDays(mt_rand(0, (int) $this->benchToday->dayOfWeek))
                : $this->today->subDays(mt_rand(0, 1825));

            $members[] = [
                'index' => $index,
                'userId' => $this->nextUserId++,
                'name' => \sprintf('Member %d-%d', $tenant['id'], $index),
                'role' => $role,
                'primary' => false,
                'start' => $start,
                'finish' => $this->futureFinish(),
            ];
        }

        return $members;
    }

    /**
     * Add the subsidiary edges that make up the root tenant tree.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedSubsidiaries(array $tenants): void
    {
        foreach ($tenants as $tenant) {
            if ($tenant['parent'] === null) {
                continue;
            }

            $parentId = $tenants[$tenant['parent']]['id'];
            $relationshipId = $this->nextRelationshipId++;
            $start = $this->today->subDays(mt_rand(30, 1825));
            $kind = SubsidiaryKind::cases()[mt_rand(0, \count(SubsidiaryKind::cases()) - 1)];

            $this->add('organization_relationships', [
                'id' => $relationshipId,
                'organization_id' => $parentId,
                'stakeholder_type' => Organization::class,
                'stakeholder_id' => $tenant['id'],
                'code' => null,
                'type' => OrganizationRelationshipType::Subsidiary->value,
                'kind' => $kind->value,
                'role' => null,
                'is_primary' => null,
                'remark' => null,
                'start_date' => $start->toDateString(),
                'finish_date' => null,
            ]);

            $this->add('organization_relationship_periods', $this->periodRow(
                $parentId,
                $relationshipId,
                Organization::class,
                $tenant['id'],
                OrganizationRelationshipType::Subsidiary,
                $start,
                null,
                null,
            ));
        }

        $this->flush();
    }

    /**
     * Give the benchmark owner memberships in other tenants.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedBenchMemberships(array $tenants): void
    {
        $targets = \array_slice(array_keys($tenants), 2, 20);

        foreach ($targets as $index) {
            $tenant = $tenants[$index];
            $relationshipId = $this->nextRelationshipId++;
            $start = $this->today->subDays(mt_rand(30, 900));
            $role = mt_rand(1, 100) <= 30 ? Role::Admin : Role::Member;

            $this->add('organization_relationships', [
                'id' => $relationshipId,
                'organization_id' => $tenant['id'],
                'stakeholder_type' => Person::class,
                'stakeholder_id' => 2,
                'code' => null,
                'type' => OrganizationRelationshipType::Member->value,
                'kind' => null,
                'role' => (string) $role->value,
                'is_primary' => null,
                'remark' => null,
                'start_date' => $start->toDateString(),
                'finish_date' => null,
            ]);

            $this->add('organization_relationship_periods', $this->periodRow(
                $tenant['id'],
                $relationshipId,
                Person::class,
                2,
                OrganizationRelationshipType::Member,
                $start,
                null,
                $role,
            ));
        }

        $this->flush();
    }

    /**
     * Create the shared counterparty pool: 40 organizations and 40 people per
     * tenant, each attached to a random group of 2-5 tenants.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedSharedContacts(array $tenants): void
    {
        $tenantIndexes = array_keys($tenants);

        foreach (['organization', 'individual'] as $type) {
            for ($slot = 1; $slot <= self::SHARED_PER_TYPE; $slot++) {
                $order = $tenantIndexes;
                shuffle($order);

                foreach ($this->groups($order) as $groupIndex => $group) {
                    $ownerId = $tenants[$group[0]]['id'];
                    $created = $this->randomCreatedAt();

                    if ($type === 'organization') {
                        $personId = $this->nextPersonId++;
                        $organizationId = $this->nextOrgId++;
                        $name = \sprintf('Shared Org %d-%d', $slot, $groupIndex);
                        $stakeholderType = Organization::class;
                        $stakeholderId = $organizationId;

                        $this->add('people', $this->personRow($personId, null, $ownerId, \sprintf('Contact %s', $name), $created));
                        $this->add('organizations', $this->organizationRow($organizationId, $personId, $ownerId, $name, $created));
                        $this->add('contact_details', $this->detailRow(Organization::class, $organizationId, 'email', \sprintf('shared-org-%d@example.test', $organizationId)));
                        $this->add('contact_details', $this->detailRow(Organization::class, $organizationId, 'phone', \sprintf('0813%06d', $organizationId % 1_000_000)));
                        $this->add('contact_details', $this->detailRow(Person::class, $personId, 'email', \sprintf('contact-%d@example.test', $personId)));

                        if (mt_rand(1, 100) <= 30) {
                            $this->add('addresses', $this->addressRow(Organization::class, $organizationId));
                        }
                    } else {
                        $personId = $this->nextPersonId++;
                        $name = \sprintf('Shared Person %d-%d', $slot, $groupIndex);
                        $stakeholderType = Person::class;
                        $stakeholderId = $personId;

                        $this->add('people', $this->personRow($personId, null, $ownerId, $name, $created));
                        $this->add('contact_details', $this->detailRow(Person::class, $personId, 'email', \sprintf('shared-person-%d@example.test', $personId)));

                        if (mt_rand(1, 100) <= 10) {
                            $this->add('addresses', $this->addressRow(Person::class, $personId));
                        }
                    }

                    foreach ($group as $tenantIndex) {
                        $this->addContactRelationship($tenants[$tenantIndex], $stakeholderType, $stakeholderId, false, 0);
                    }
                }

                $this->flush();
            }
        }
    }

    /**
     * Give every tenant its own non-shared contacts.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedUniqueContacts(array $tenants): void
    {
        foreach ($tenants as $tenant) {
            for ($index = 0; $index < $tenant['orgContacts'] - self::SHARED_PER_TYPE; $index++) {
                $personId = $this->nextPersonId++;
                $organizationId = $this->nextOrgId++;
                $created = $this->randomCreatedAt();
                $name = \sprintf('Org %d-%d', $tenant['id'], $index + 1);

                $this->add('people', $this->personRow($personId, null, $tenant['id'], \sprintf('Contact %s', $name), $created));
                $this->add('organizations', $this->organizationRow($organizationId, $personId, $tenant['id'], $name, $created));
                $this->add('contact_details', $this->detailRow(Organization::class, $organizationId, 'email', \sprintf('org-%d@example.test', $organizationId)));
                $this->add('contact_details', $this->detailRow(Organization::class, $organizationId, 'phone', \sprintf('0814%06d', $organizationId % 1_000_000)));

                if (mt_rand(1, 100) <= 30) {
                    $this->add('addresses', $this->addressRow(Organization::class, $organizationId));
                }

                $this->addContactRelationship($tenant, Organization::class, $organizationId, $tenant['isBench'], $index);
            }

            for ($index = 0; $index < $tenant['individualContacts'] - self::SHARED_PER_TYPE; $index++) {
                $personId = $this->nextPersonId++;
                $created = $this->randomCreatedAt();
                $name = \sprintf('Person %d-%d', $tenant['id'], $index + 1);

                $this->add('people', $this->personRow($personId, null, $tenant['id'], $name, $created));
                $this->add('contact_details', $this->detailRow(Person::class, $personId, 'email', \sprintf('person-%d@example.test', $personId)));

                if (mt_rand(1, 100) <= 10) {
                    $this->add('addresses', $this->addressRow(Person::class, $personId));
                }

                $this->addContactRelationship($tenant, Person::class, $personId, $tenant['isBench'], $index);
            }

            $this->flush();
        }
    }

    /**
     * Attach a contact to a tenant with its own relationship window.
     *
     * @param  array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}  $tenant
     */
    private function addContactRelationship(array $tenant, string $stakeholderType, int $stakeholderId, bool $benchWindow, int $index): void
    {
        [$start, $finish] = $benchWindow
            ? $this->benchContactDates($index)
            : $this->contactDates();

        $relationshipId = $this->nextRelationshipId++;
        $type = self::EXTERNAL_TYPES[mt_rand(0, \count(self::EXTERNAL_TYPES) - 1)];

        $this->add('organization_relationships', [
            'id' => $relationshipId,
            'organization_id' => $tenant['id'],
            'stakeholder_type' => $stakeholderType,
            'stakeholder_id' => $stakeholderId,
            'code' => null,
            'type' => $type->value,
            'kind' => null,
            'role' => null,
            'is_primary' => null,
            'remark' => null,
            'start_date' => $start->toDateString(),
            'finish_date' => $finish?->toDateString(),
        ]);

        $this->add('organization_relationship_periods', $this->periodRow(
            $tenant['id'],
            $relationshipId,
            $stakeholderType,
            $stakeholderId,
            $type,
            $start,
            $finish,
            null,
        ));
    }

    /**
     * Seed the notification inbox for the two known users.
     */
    private function seedNotifications(): void
    {
        $rows = [];
        $now = CarbonImmutable::now('Asia/Jakarta');

        foreach ([[2, 20_000, 5_000], [1, 5_000, 0]] as [$userId, $unreadCount, $readCount]) {
            for ($index = 0; $index < $unreadCount + $readCount; $index++) {
                $created = $index < 200
                    ? $now->subMinutes(mt_rand(0, 120))
                    : $now->subMinutes(mt_rand(0, 43_200));
                $read = $index >= $unreadCount;

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\SeededNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $userId,
                    'data' => json_encode([
                        'title' => \sprintf('Notification %d', $index + 1),
                        'description' => \sprintf('Seeded notification %d', $index + 1),
                    ], JSON_THROW_ON_ERROR),
                    'read_at' => $read ? $created->addMinutes(5) : null,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];

                if (\count($rows) >= 1000) {
                    $this->insertRows('notifications', $rows);
                    $rows = [];
                }
            }
        }

        $this->insertRows('notifications', $rows);
    }

    /**
     * Seed a year of audit history, with extra volume on the benchmark tenant.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedAuditLogs(array $tenants): void
    {
        $events = array_map(fn (AuditEvent $event): string => $event->value, AuditEvent::cases());
        $rows = [];

        foreach ($tenants as $tenant) {
            $count = $tenant['isBench'] ? 1000 : ($tenant['isRoot'] ? 500 : 10);
            $actorId = $this->ownerPersonIds[$tenant['id']];

            for ($index = 0; $index < $count; $index++) {
                $created = $this->today->subMinutes(mt_rand(0, 525_600));

                $rows[] = [
                    'organization_id' => $tenant['id'],
                    'actor_id' => $actorId,
                    'event' => $events[mt_rand(0, \count($events) - 1)],
                    'subject_type' => Organization::class,
                    'subject_id' => $tenant['id'],
                    'organization_relationship_period_id' => null,
                    'changes' => null,
                    'created_at' => $created,
                ];

                if (\count($rows) >= 1000) {
                    $this->insertRows('audit_logs', $rows);
                    $rows = [];
                }
            }
        }

        $this->insertRows('audit_logs', $rows);
    }

    /**
     * Seed approval history, with one pending request where allowed.
     *
     * @param  list<array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}>  $tenants
     */
    private function seedApprovals(array $tenants): void
    {
        $rows = [];

        foreach ($tenants as $tenant) {
            $count = $tenant['isBench'] ? 500 : 3;
            $ownerId = $this->ownerPersonIds[$tenant['id']];
            $members = $this->memberPersonIds[$tenant['id']];
            $otherId = \count($members) > 1 ? $members[1] : $ownerId;

            for ($index = 0; $index < $count; $index++) {
                $status = [
                    ApprovalRequestStatus::Accepted,
                    ApprovalRequestStatus::Declined,
                    ApprovalRequestStatus::Canceled,
                ][$index % 3];

                $created = $this->today->subDays(mt_rand(1, 365));
                $requester = $index % 2 === 0 ? $ownerId : $otherId;
                $approver = $index % 2 === 0 ? $otherId : $ownerId;

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'organization_id' => $tenant['id'],
                    'type' => ($index % 2 === 0
                        ? ApprovalRequestType::OwnershipTransfer
                        : ApprovalRequestType::MembershipLeave)->value,
                    'requester_person_id' => $requester,
                    'approver_person_id' => $approver,
                    'status' => $status->value,
                    'payload' => null,
                    'expires_at' => $created->addDays(7),
                    'accepted_at' => $status === ApprovalRequestStatus::Accepted ? $created->addDay() : null,
                    'declined_at' => $status === ApprovalRequestStatus::Declined ? $created->addDay() : null,
                    'canceled_at' => $status === ApprovalRequestStatus::Canceled ? $created->addDay() : null,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }

            if ($tenant['isBench'] || $tenant['id'] % 2 === 0) {
                $created = $this->today->subDay();

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'organization_id' => $tenant['id'],
                    'type' => ApprovalRequestType::OwnershipTransfer->value,
                    'requester_person_id' => $tenant['isBench'] ? 2 : $ownerId,
                    'approver_person_id' => $otherId,
                    'status' => ApprovalRequestStatus::Pending->value,
                    'payload' => null,
                    'expires_at' => $this->today->addDays(7),
                    'accepted_at' => null,
                    'declined_at' => null,
                    'canceled_at' => null,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }

            if (\count($rows) >= 1000) {
                $this->insertRows('approval_requests', $rows);
                $rows = [];
            }
        }

        $this->insertRows('approval_requests', $rows);
    }

    /**
     * Split a shuffled tenant list into groups of 2-5.
     *
     * @param  list<int>  $order
     * @return list<list<int>>
     */
    private function groups(array $order): array
    {
        $groups = [];
        $count = \count($order);
        $offset = 0;

        while ($offset < $count) {
            $size = min(mt_rand(2, 5), $count - $offset);

            if ($count - $offset - $size === 1) {
                $size++;
            }

            $groups[] = \array_slice($order, $offset, $size);
            $offset += $size;
        }

        return $groups;
    }

    /**
     * The current week's dates for the benchmark dashboard rows.
     *
     * @return array{0: CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function benchContactDates(int $index): array
    {
        $todayOffset = (int) $this->benchToday->dayOfWeek;

        if ($index < 5) {
            return [$this->benchWeekStart->addDays(mt_rand(0, $todayOffset)), null];
        }

        if ($index < 10) {
            return [
                $this->benchToday->subDays(mt_rand(30, 365)),
                $this->benchWeekStart->addDays(mt_rand($todayOffset, 6)),
            ];
        }

        if ($index < 15) {
            return [
                $this->benchToday->subDays(mt_rand(400, 900)),
                $this->benchToday->subDays(mt_rand(1, 30)),
            ];
        }

        return $this->contactDates();
    }

    /**
     * A random start and finish window for a contact.
     *
     * @return array{0: CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function contactDates(): array
    {
        $start = $this->today->subDays(mt_rand(0, 1825));
        $roll = mt_rand(1, 100);

        $finish = match (true) {
            $roll <= 60 => null,
            $roll <= 75 => $this->today->addDays(mt_rand(1, 90)),
            default => $this->today->subDays(mt_rand(1, 730)),
        };

        if ($finish !== null && $finish->lessThan($start)) {
            $start = $finish->subDays(mt_rand(30, 300));
        }

        return [$start, $finish];
    }

    /**
     * A mostly-null finish date for members.
     */
    private function futureFinish(): ?CarbonImmutable
    {
        return mt_rand(1, 100) <= 5
            ? $this->today->addDays(mt_rand(30, 180))
            : null;
    }

    private function randomCreatedAt(): CarbonImmutable
    {
        return $this->today
            ->subDays(mt_rand(0, 1825))
            ->setTime(mt_rand(0, 23), mt_rand(0, 59));
    }

    /**
     * @param  array{id: int, name: string, isRoot: bool, isBench: bool, members: int, orgContacts: int, individualContacts: int, parent: ?int, depth: int}  $tenant
     */
    private function tenantCreatedAt(array $tenant): CarbonImmutable
    {
        return match (true) {
            $tenant['isRoot'] => $this->today->subYears(5),
            $tenant['isBench'] => $this->today->subYears(3),
            default => $this->today->subDays(mt_rand(30, 1825)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(int $id, string $handle, string $email, CarbonImmutable $created): array
    {
        return [
            'id' => $id,
            'handle' => $handle,
            'email' => $email,
            'email_verified_at' => $created,
            'password' => $this->password,
            'locale' => null,
            'timezone' => 'Asia/Jakarta',
            'preferences' => null,
            'remember_token' => null,
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personRow(int $id, ?int $userId, ?int $ownerOrganizationId, string $name, CarbonImmutable $created): array
    {
        return [
            'id' => $id,
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'owner_organization_id' => $ownerOrganizationId,
            'name' => $name,
            'gender' => null,
            'photo_url' => null,
            'summary' => null,
            'created_at' => $created,
            'updated_at' => $created,
            'deleted_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationRow(int $id, int $contactId, int $ownerId, string $name, CarbonImmutable $created): array
    {
        return [
            'id' => $id,
            'uuid' => (string) Str::uuid(),
            'contact_id' => $contactId,
            'owner_organization_id' => $ownerId,
            'root_organization_id' => null,
            'is_tenant' => false,
            'name' => $name,
            'summary' => null,
            'logo_url' => null,
            'created_at' => $created,
            'updated_at' => $created,
            'deleted_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRow(string $type, int $id, string $detailType, string $value): array
    {
        return [
            'contactable_type' => $type,
            'contactable_id' => $id,
            'type' => $detailType,
            'value' => $value,
            'created_at' => $this->today,
            'updated_at' => $this->today,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressRow(string $type, int $id): array
    {
        return [
            'addressable_type' => $type,
            'addressable_id' => $id,
            'line' => \sprintf('Jl. Example No. %d', $id),
            'rt' => null,
            'rw' => null,
            'village_code' => null,
            'district_code' => null,
            'regency_code' => '3171',
            'province_code' => '31',
            'postal_code' => '12345',
            'created_at' => $this->today,
            'updated_at' => $this->today,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodRow(
        int $organizationId,
        int $relationshipId,
        string $stakeholderType,
        int $stakeholderId,
        OrganizationRelationshipType $type,
        CarbonImmutable $start,
        ?CarbonImmutable $finish,
        ?Role $role,
    ): array {
        return [
            'organization_id' => $organizationId,
            'organization_relationship_id' => $relationshipId,
            'stakeholder_type' => $stakeholderType,
            'stakeholder_id' => $stakeholderId,
            'type' => $type->value,
            'code' => null,
            'role' => $role === null ? null : (string) $role->value,
            'start_date' => $start->toDateString(),
            'finish_date' => $finish?->toDateString(),
            'reason' => null,
            'actor_id' => null,
            'closed_at' => null,
            'created_at' => $start,
            'updated_at' => $start,
        ];
    }

    /**
     * Buffer a row and flush the table when the batch is full.
     *
     * @param  array<string, mixed>  $row
     */
    private function add(string $table, array $row): void
    {
        $this->buffer[$table][] = $row;

        if (\count($this->buffer[$table]) >= 1000) {
            $this->insertRows($table, $this->buffer[$table]);
            $this->buffer[$table] = [];
        }
    }

    /**
     * Flush every buffered table in foreign-key order.
     */
    private function flush(): void
    {
        foreach ($this->buffer as $table => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->insertRows($table, $rows);
            $this->buffer[$table] = [];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function resetSequences(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['users', 'people', 'organizations', 'organization_relationships'] as $table) {
            DB::statement(\sprintf(
                "SELECT setval(pg_get_serial_sequence('%s', 'id'), COALESCE((SELECT MAX(id) FROM %s), 1))",
                $table,
                $table,
            ));
        }
    }

    private function analyze(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ANALYZE');
        }
    }

    private function abort(string $message): void
    {
        if ($this->command !== null) {
            $this->command->error($message);
        }

        throw new RuntimeException($message);
    }

    private function report(int $tenantCount, float $elapsed): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->info(\sprintf(
            'Seeded %d tenants in %.1fs: %d users, %d people, %d organizations, %d relationships, %d notifications.',
            $tenantCount,
            $elapsed,
            DB::table('users')->count(),
            DB::table('people')->count(),
            DB::table('organizations')->count(),
            DB::table('organization_relationships')->count(),
            DB::table('notifications')->count(),
        ));

        $this->command->info('Logins: rootowner / benchowner, password: password');
        $this->command->info('Benchmark tenant: id 2 (Benchmark Tenant), 50 members, 200 contacts.');
    }
}
