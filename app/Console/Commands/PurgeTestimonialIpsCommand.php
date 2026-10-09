<?php

namespace App\Console\Commands;

use App\Services\Testimonials\TestimonialModerationService;
use Illuminate\Console\Command;

class PurgeTestimonialIpsCommand extends Command
{
    protected $signature = 'pacms:testimonials:purge-ips';

    protected $description = 'Remove the IP addresses of testimonial submissions older than the retention period';

    public function handle(TestimonialModerationService $testimonials): int
    {
        $count = $testimonials->purgeIps();

        if ($count > 0) {
            $this->components->info("Removed the IP address of {$count} testimonial(s).");
        }

        return self::SUCCESS;
    }
}
