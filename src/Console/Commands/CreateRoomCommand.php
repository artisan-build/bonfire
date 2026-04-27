<?php

declare(strict_types=1);

namespace ArtisanBuild\Bonfire\Console\Commands;

use ArtisanBuild\Bonfire\Facades\Bonfire;
use ArtisanBuild\Bonfire\Models\Member;
use ArtisanBuild\Bonfire\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates a Bonfire room from the command line.
 */
class CreateRoomCommand extends Command
{
    protected $signature = 'bonfire:create-room {name : Room display name}
        {--type=0 : RoomType bitmask value (0=public, 1=private, 2=archived, 4=announcements)}
        {--description= : Optional description}
        {--slug= : Optional slug override}
        {--member-id= : Member ID to record as creator (defaults to first admin)}';

    protected $description = 'Create a Bonfire room from the command line.';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $type = (int) $this->option('type');

        $tenantId = Bonfire::tenantId();
        $creatorId = $this->resolveCreatorId($tenantId);

        if ($creatorId === null) {
            $this->components->error('No member found to set as creator. Create an admin member first or pass --member-id.');

            return self::FAILURE;
        }

        $slug = (string) ($this->option('slug') ?: Str::slug($name).'-'.Str::lower(Str::random(6)));

        $room = Room::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'description' => $this->option('description') ?: null,
            'type' => $type,
            'created_by' => $creatorId,
        ]);

        $this->components->success("Created room #{$room->id}: {$room->name} ({$room->slug})");

        return self::SUCCESS;
    }

    private function resolveCreatorId(?int $tenantId): ?int
    {
        $override = $this->option('member-id');

        if ($override !== null) {
            return (int) $override;
        }

        return Member::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');
    }
}
