<?php

namespace App\Console\Commands;

use App\Cms\Content\ContentTypeRegistry;
use App\Services\Content\ContentService;
use App\Services\Publishing\PublishingService;
use Illuminate\Console\Command;

class PublishScheduledCommand extends Command
{
    protected $signature = 'pacms:publish-scheduled';

    protected $description = 'Publish approved pages and content items whose scheduled publish time has passed';

    public function handle(PublishingService $publishing, ContentService $content, ContentTypeRegistry $types): int
    {
        $count = $publishing->publishDue();

        foreach ($types->all() as $type) {
            $count += $content->publishDue($type);
        }

        if ($count > 0) {
            $this->components->info("Published {$count} scheduled item(s).");
        }

        return self::SUCCESS;
    }
}
