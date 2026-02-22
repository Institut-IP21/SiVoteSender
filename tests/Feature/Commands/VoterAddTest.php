<?php

namespace Tests\Feature\Commands;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterAddTest extends TestCase
{
    use RefreshDatabase;

    public function testAddSingleVoter()
    {
        $voterList = VoterList::factory()->create(['title' => 'My List']);

        $this->artisan('evote:add:voter', [
            '--voterlist' => $voterList->id,
            '--title' => 'Jane Doe',
            '--email' => 'jane@example.com',
            '--phone' => '+1234567890',
        ])
            ->expectsOutput("Added voter 'Jane Doe' to voter list 'My List'")
            ->assertExitCode(0);

        $this->assertDatabaseHas('voters', [
            'title' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1234567890',
        ]);

        $this->assertEquals(1, $voterList->voters()->count());
    }

    public function testAddFromCsv()
    {
        $voterList = VoterList::factory()->create(['title' => 'CSV List']);

        $csvContent = "title,email,phone\nAlice,alice@example.com,+111\nBob,bob@example.com,\nCharlie,charlie@example.com,+333\n";
        $csvPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csvPath, $csvContent);

        $this->artisan('evote:add:voter', [
            '--voterlist' => $voterList->id,
            '--csv' => $csvPath,
        ])
            ->expectsOutput("Added 3 voters to voter list 'CSV List'")
            ->assertExitCode(0);

        $this->assertEquals(3, $voterList->voters()->count());
        $this->assertDatabaseHas('voters', ['title' => 'Alice', 'email' => 'alice@example.com']);
        $this->assertDatabaseHas('voters', ['title' => 'Bob', 'email' => 'bob@example.com']);
        $this->assertDatabaseHas('voters', ['title' => 'Charlie', 'email' => 'charlie@example.com']);

        unlink($csvPath);
    }

    public function testAddToInvalidList()
    {
        $this->artisan('evote:add:voter', [
            '--voterlist' => 999,
            '--title' => 'Nobody',
            '--email' => 'nobody@example.com',
        ])
            ->expectsOutput('Voter list with ID 999 not found.')
            ->assertExitCode(1);
    }

    public function testAddCsvFileNotFound()
    {
        $voterList = VoterList::factory()->create();

        $this->artisan('evote:add:voter', [
            '--voterlist' => $voterList->id,
            '--csv' => '/nonexistent/file.csv',
        ])
            ->expectsOutput('CSV file not found: /nonexistent/file.csv')
            ->assertExitCode(1);
    }
}
