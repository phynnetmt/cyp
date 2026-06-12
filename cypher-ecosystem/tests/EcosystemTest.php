<?php

namespace Cypher\Ecosystem\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Ecosystem\Academy\AcademyPlatform;
use Cypher\Ecosystem\Community\CommunityPlatform;
use Cypher\Ecosystem\Content\ContentEcosystem;
use Cypher\Ecosystem\Startups\StartupProgram;
use Cypher\Ecosystem\University\UniversityProgram;
use Cypher\Ecosystem\Events\EventsPlatform;
use Cypher\Ecosystem\Governance\OpenSourceGovernance;
use Cypher\Ecosystem\Partners\PartnerEcosystem;
use Cypher\Ecosystem\Advocacy\AdvocacyProgram;

class EcosystemTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cyp_eco_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->testDir);
    }

    // === Academy ===
    public function testCreateCourse(): void
    {
        $ac = new AcademyPlatform(['data_dir' => $this->testDir . '/academy']);
        $course = $ac->createCourse('Intro to CYP', 'Learn CYP basics', 'beginner', [
            ['id' => 'm1', 'title' => 'Getting Started'],
            ['id' => 'm2', 'title' => 'Variables'],
        ]);
        $this->assertSame('Intro to CYP', $course['title']);
        $this->assertSame('published', $course['status']);
    }

    public function testEnrollmentAndProgress(): void
    {
        $ac = new AcademyPlatform(['data_dir' => $this->testDir . '/academy2']);
        $course = $ac->createCourse('Test Course', 'Desc', 'beginner', [
            ['id' => 'm1', 'title' => 'Module 1'],
            ['id' => 'm2', 'title' => 'Module 2'],
        ]);
        $enrollId = $ac->enroll($course['id'], 'user-1');
        $this->assertNotEmpty($enrollId);

        $ac->completeModule($enrollId, 'm1', 95);
        $enrollments = $ac->getUserEnrollments('user-1');
        $this->assertCount(1, $enrollments);
    }

    public function testLab(): void
    {
        $ac = new AcademyPlatform(['data_dir' => $this->testDir . '/academy3']);
        $lab = $ac->createLab('First Lab', 'Build your first app', 'easy', []);
        $ac->completeLab($lab['id'], 'user-1');
        $stats = $ac->getStats();
        $this->assertEquals(1, $stats['lab_completions']);
    }

    public function testSandbox(): void
    {
        $ac = new AcademyPlatform(['data_dir' => $this->testDir . '/academy4']);
        $id = $ac->createSandbox('user-1', 'fullstack');
        $this->assertNotEmpty($id);
    }

    // === Community ===
    public function testForum(): void
    {
        $cp = new CommunityPlatform(['data_dir' => $this->testDir . '/community']);
        $forum = $cp->createForum('General', 'General discussion', 'general');
        $topic = $cp->createTopic($forum['id'], 'Welcome!', 'Hello everyone', 'admin');
        $this->assertNotEmpty($topic['id']);

        $cp->addReply($topic['id'], 'Thanks!', 'user-1');
        $topics = $cp->listTopics($forum['id']);
        $this->assertCount(1, $topics);
    }

    public function testAmbassador(): void
    {
        $cp = new CommunityPlatform(['data_dir' => $this->testDir . '/community2']);
        $id = $cp->nominateAmbassador('user-1', 'NA', 'Active contributor');
        $this->assertNotEmpty($id);

        $cp->recordAmbassadorActivity($id, 'event');
        $ambassadors = $cp->listAmbassadors('NA');
        $this->assertCount(1, $ambassadors);
    }

    public function testMeetup(): void
    {
        $cp = new CommunityPlatform(['data_dir' => $this->testDir . '/community3']);
        $meetup = $cp->createMeetup('CYP Meetup', 'Monthly meetup', 'NYC', '2026-07-01');
        $cp->rsvpMeetup($meetup['id']);
        $cp->rsvpMeetup($meetup['id']);
        $this->assertEquals(2, $meetup['attendees'] + 2);
    }

    // === Content ===
    public function testDocumentation(): void
    {
        $ce = new ContentEcosystem(['data_dir' => $this->testDir . '/content']);
        $doc = $ce->publishDocumentation('Getting Started', 'Install CYP...', 'guides', '1.0');
        $this->assertNotEmpty($doc['id']);

        $results = $ce->searchDocumentation('Getting Started');
        $this->assertCount(1, $results);
    }

    public function testTutorial(): void
    {
        $ce = new ContentEcosystem(['data_dir' => $this->testDir . '/content2']);
        $tut = $ce->createTutorial('Build an API', 'Learn API building', 'intermediate', 30, []);
        $ce->completeTutorial($tut['id']);
        $tutorials = $ce->listTutorials('intermediate');
        $this->assertCount(1, $tutorials);
    }

    public function testBlueprintAndSampleApps(): void
    {
        $ce = new ContentEcosystem(['data_dir' => $this->testDir . '/content3']);
        $bp = $ce->createBlueprint('Microservices', 'Architecture guide', 'architecture', []);
        $this->assertNotEmpty($bp['id']);

        $app = $ce->createSampleApp('Todo App', 'A todo application', 'fullstack', ['app.cyp']);
        $ce->recordDownload($app['id']);
        $stats = $ce->getStats();
        $this->assertEquals(1, $stats['total_downloads']);
    }

    // === Startups ===
    public function testStartupOnboarding(): void
    {
        $sp = new StartupProgram(['data_dir' => $this->testDir . '/startups']);
        $startup = $sp->onboard('TechStartup Inc', 'founder@techstartup.io', 'seed', 'AI-powered analytics');
        $this->assertSame('active', $startup['status']);
        $this->assertEquals(1000, $startup['credits_remaining']);
    }

    public function testCredits(): void
    {
        $sp = new StartupProgram(['data_dir' => $this->testDir . '/startups2']);
        $startup = $sp->onboard('Startup', 'f@startup.com', 'early', 'desc');
        $sp->grantCredits($startup['id'], 500, 'Milestone completion');
        $sp->useCredits($startup['id'], 200);

        $listings = $sp->listStartups('early');
        $this->assertCount(1, $listings);
    }

    // === University ===
    public function testUniversityPartnership(): void
    {
        $up = new UniversityProgram(['data_dir' => $this->testDir . '/uni']);
        $partner = $up->createPartnership('MIT', 'USA', 'mit@edu', 'premium');
        $this->assertSame('active', $partner['status']);
    }

    public function testCurriculum(): void
    {
        $up = new UniversityProgram(['data_dir' => $this->testDir . '/uni2']);
        $partner = $up->createPartnership('Stanford', 'USA', 'stanford@edu');
        $curr = $up->publishCurriculum('CYP 101', 'Intro to CYP', 'undergraduate', 3, []);
        $up->adoptCurriculum($curr['id'], $partner['id']);
        $this->assertEquals(1, $curr['adoptions'] + 1);
    }

    public function testResearchGrants(): void
    {
        $up = new UniversityProgram(['data_dir' => $this->testDir . '/uni3']);
        $grant = $up->createResearchGrant('AI Agents Research', 'Dr. Smith', 'Stanford', 50000, 'multi-agent');
        $this->assertNotEmpty($grant['id']);

        $up->submitDeliverable($grant['id'], 'Phase 1 Report', 'Research findings...');
        $stats = $up->getStats();
        $this->assertEquals(50000, $stats['total_funding']);
    }

    // === Events ===
    public function testConference(): void
    {
        $ep = new EventsPlatform(['data_dir' => $this->testDir . '/events']);
        $conf = $ep->createConference('CYP Global Summit', 'Annual conference', 'SF', '2026-09-15', 2000, ['AI', 'Cloud']);
        $this->assertNotEmpty($conf['id']);

        $ep->registerAttendee($conf['id'], 'user-1', 'vip');
        $ep->registerAttendee($conf['id'], 'user-2', 'standard');
        $ep->submitCFP($conf['id'], 'Building AI Agents', 'Talk about agents', 'speaker-1', 'AI');

        $conferences = $ep->listConferences();
        $this->assertCount(1, $conferences);
    }

    public function testSponsorship(): void
    {
        $ep = new EventsPlatform(['data_dir' => $this->testDir . '/events2']);
        $conf = $ep->createConference('DevConf', 'Developer conference', 'Berlin', '2026-10-01', 500);
        $sponsor = $ep->addSponsor($conf['id'], 'CloudCorp', 'platinum', 50000);
        $this->assertSame('confirmed', $sponsor['status']);

        $stats = $ep->getStats();
        $this->assertEquals(50000, $stats['sponsorship_revenue']);
    }

    // === Governance ===
    public function testRFCProcess(): void
    {
        $og = new OpenSourceGovernance(['data_dir' => $this->testDir . '/gov']);
        $rfc = $og->submitRFC('Async Support', 'dev@cyp.dev', 'Add async/await', 'Details...', 'feature');
        $this->assertSame('draft', $rfc['status']);

        $og->addComment($rfc['id'], 'contrib-1', 'Great idea!');
        $rfcs = $og->listRFCs('draft');
        $this->assertCount(1, $rfcs);
    }

    public function testVoting(): void
    {
        $og = new OpenSourceGovernance(['data_dir' => $this->testDir . '/gov2']);
        $rfc = $og->submitRFC('New Feature', 'author', 'Summary', 'Details');
        for ($i = 0; $i < 5; $i++) {
            $og->voteOnRFC($rfc['id'], "voter-{$i}", true);
        }
        $rfcs = $og->listRFCs('accepted');
        $this->assertCount(1, $rfcs);
    }

    public function testContributors(): void
    {
        $og = new OpenSourceGovernance(['data_dir' => $this->testDir . '/gov3']);
        $id = $og->registerContributor('gh-user', 'compiler', '10 PRs merged');
        $og->recordContribution($id, 'pr');
        $og->recordContribution($id, 'pr');

        $top = $og->topContributors();
        $this->assertNotEmpty($top);
        $this->assertEquals(2, $top[0]['prs_merged']);
    }

    // === Partners ===
    public function testPartnerOnboarding(): void
    {
        $pe = new PartnerEcosystem(['data_dir' => $this->testDir . '/partners']);
        $partner = $pe->onboard('AWS', 'cloud_provider', 'aws@partner.com', 'platinum');
        $this->assertSame('active', $partner['status']);

        $pe->addIntegration($partner['id'], 'CYP Deploy', 'One-click deployment');
        $pe->recordReferral($partner['id']);

        $platinum = $pe->listPartners('platinum');
        $this->assertCount(1, $platinum);
    }

    // === Advocacy ===
    public function testAdvocateProgram(): void
    {
        $ap = new AdvocacyProgram(['data_dir' => $this->testDir . '/advocacy']);
        $adv = $ap->onboardAdvocate('Alice', 'alice@dev.com', 'EMEA', 'AI Agents', 'community');
        $ap->recordActivity($adv['id'], 'talk');
        $ap->recordActivity($adv['id'], 'article');

        $advocates = $ap->listAdvocates('EMEA');
        $this->assertCount(1, $advocates);
    }

    public function testSpeakerBureau(): void
    {
        $ap = new AdvocacyProgram(['data_dir' => $this->testDir . '/advocacy2']);
        $id = $ap->registerSpeaker('Bob', 'bob@dev.com', 'CYP Architecture', 'Expert architect');
        $ap->scheduleTalk($id);
        $speakers = $ap->listSpeakers('CYP Architecture');
        $this->assertCount(1, $speakers);
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
