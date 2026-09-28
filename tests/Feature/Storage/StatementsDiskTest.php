<?php

namespace Tests\Feature\Storage;

use App\Support\StatementStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatementsDiskTest extends TestCase
{
    public function test_statements_disk_is_configured_under_private_storage(): void
    {
        $this->assertSame(
            storage_path('app/private'),
            config('filesystems.disks.local.root')
        );

        $this->assertSame(
            storage_path('app/private/statements'),
            config('filesystems.disks.statements.root')
        );

        $this->assertSame('private', config('filesystems.disks.statements.visibility'));
        $this->assertTrue(config('filesystems.disks.statements.throw'));
        $this->assertFalse((bool) config('filesystems.disks.statements.serve'));
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
    }

    public function test_statements_files_are_not_served_as_downloadable_content(): void
    {
        Storage::fake(StatementStorage::DISK);

        $relative = 'smoke/secret.csv';
        $csv = "Data,Valor\n01/01/2026,-10,00\n";

        Storage::disk(StatementStorage::DISK)->put($relative, $csv);
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($relative));

        foreach ([
            '/storage/'.$relative,
            '/storage/statements/'.$relative,
            '/storage/app/private/statements/'.$relative,
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_public_disk_is_not_used_as_statements_root(): void
    {
        $statementsRoot = str_replace('\\', '/', config('filesystems.disks.statements.root'));
        $publicRoot = str_replace('\\', '/', config('filesystems.disks.public.root'));

        $this->assertNotSame($publicRoot, $statementsRoot);
        $this->assertStringContainsString('/private/statements', $statementsRoot);

        // storage:link only maps public disk — never private/statements.
        $this->assertSame(
            [public_path('storage') => storage_path('app/public')],
            config('filesystems.links')
        );
    }
}
