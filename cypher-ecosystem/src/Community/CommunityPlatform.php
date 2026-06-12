<?php

namespace Cypher\Ecosystem\Community;

class CommunityPlatform
{
    private array $forums = [];
    private array $topics = [];
    private array $ambassadors = [];
    private array $meetups = [];
    private array $discordConfig = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/community');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createForum(string $name, string $description, string $category): array
    {
        $id = uniqid('forum_', true);
        $forum = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'topic_count' => 0,
            'status' => 'active',
            'created_at' => date('c'),
        ];
        $this->forums[$id] = $forum;
        $this->save();
        return $forum;
    }

    public function createTopic(string $forumId, string $title, string $content, string $author): array
    {
        if (!isset($this->forums[$forumId])) {
            throw new CommunityException("Forum not found");
        }

        $id = uniqid('topic_', true);
        $topic = [
            'id' => $id,
            'forum_id' => $forumId,
            'title' => $title,
            'content' => $content,
            'author' => $author,
            'replies' => 0,
            'views' => 0,
            'status' => 'active',
            'created_at' => date('c'),
            'last_activity' => date('c'),
        ];

        $this->topics[$id] = $topic;
        $this->forums[$forumId]['topic_count']++;
        $this->save();

        return $topic;
    }

    public function addReply(string $topicId, string $content, string $author): void
    {
        $topic = $this->topics[$topicId] ?? null;
        if (!$topic) {
            throw new CommunityException("Topic not found");
        }

        $this->topics[$topicId]['replies']++;
        $this->topics[$topicId]['last_activity'] = date('c');
        $this->save();
    }

    public function listForums(string $category = ''): array
    {
        if ($category) {
            return array_values(array_filter($this->forums, fn($f) => $f['category'] === $category));
        }
        return array_values($this->forums);
    }

    public function listTopics(string $forumId = ''): array
    {
        if ($forumId) {
            $topics = array_filter($this->topics, fn($t) => $t['forum_id'] === $forumId);
            usort($topics, fn($a, $b) => $b['last_activity'] <=> $a['last_activity']);
            return array_values($topics);
        }
        return array_values($this->topics);
    }

    public function nominateAmbassador(string $userId, string $region, string $reason): string
    {
        $id = uniqid('amb_', true);
        $this->ambassadors[$id] = [
            'id' => $id,
            'user_id' => $userId,
            'region' => $region,
            'reason' => $reason,
            'status' => 'active',
            'events_hosted' => 0,
            'members_recruited' => 0,
            'nominated_at' => date('c'),
        ];
        $this->save();
        return $id;
    }

    public function recordAmbassadorActivity(string $ambassadorId, string $type): void
    {
        if (isset($this->ambassadors[$ambassadorId])) {
            if ($type === 'event') $this->ambassadors[$ambassadorId]['events_hosted']++;
            if ($type === 'recruit') $this->ambassadors[$ambassadorId]['members_recruited']++;
            $this->save();
        }
    }

    public function listAmbassadors(string $region = ''): array
    {
        if ($region) {
            return array_values(array_filter($this->ambassadors, fn($a) => $a['region'] === $region));
        }
        return array_values($this->ambassadors);
    }

    public function createMeetup(string $title, string $description, string $location, string $date, int $capacity = 50): array
    {
        $id = uniqid('mtup_', true);
        $meetup = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'date' => $date,
            'capacity' => $capacity,
            'attendees' => 0,
            'status' => 'upcoming',
            'created_at' => date('c'),
        ];
        $this->meetups[$id] = $meetup;
        $this->save();
        return $meetup;
    }

    public function rsvpMeetup(string $meetupId): void
    {
        if (isset($this->meetups[$meetupId])) {
            $this->meetups[$meetupId]['attendees']++;
            if ($this->meetups[$meetupId]['attendees'] >= $this->meetups[$meetupId]['capacity']) {
                $this->meetups[$meetupId]['status'] = 'full';
            }
            $this->save();
        }
    }

    public function listMeetups(string $status = ''): array
    {
        if ($status) {
            return array_values(array_filter($this->meetups, fn($m) => $m['status'] === $status));
        }
        return array_values($this->meetups);
    }

    public function configureDiscord(string $serverId, string $inviteLink, array $channels = []): void
    {
        $this->discordConfig = [
            'server_id' => $serverId,
            'invite_link' => $inviteLink,
            'channels' => $channels,
            'configured_at' => date('c'),
        ];
        $this->save();
    }

    public function getDiscordConfig(): array
    {
        return $this->discordConfig;
    }

    public function getStats(): array
    {
        return [
            'forums' => count($this->forums),
            'topics' => count($this->topics),
            'ambassadors' => count($this->ambassadors),
            'meetups' => count($this->meetups),
            'discord_configured' => !empty($this->discordConfig),
        ];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/community.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->forums = $data['forums'] ?? [];
                $this->topics = $data['topics'] ?? [];
                $this->ambassadors = $data['ambassadors'] ?? [];
                $this->meetups = $data['meetups'] ?? [];
                $this->discordConfig = $data['discord'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/community.json',
            json_encode([
                'forums' => $this->forums,
                'topics' => $this->topics,
                'ambassadors' => $this->ambassadors,
                'meetups' => $this->meetups,
                'discord' => $this->discordConfig,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
