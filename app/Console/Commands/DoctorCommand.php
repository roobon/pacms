<?php

namespace App\Console\Commands;

use App\Services\System\HealthCheck;
use Illuminate\Console\Command;

class DoctorCommand extends Command
{
    protected $signature = 'pacms:doctor';

    protected $description = 'Check that this PACMS installation is configured correctly';

    public function handle(HealthCheck $health): int
    {
        $results = $health->run();

        foreach ($results as $result) {
            $icon = match ($result['status']) {
                HealthCheck::OK => '<fg=green>✔</>',
                HealthCheck::WARNING => '<fg=yellow>!</>',
                default => '<fg=red>✘</>',
            };

            $this->line(sprintf(' %s <options=bold>%s</>: %s', $icon, $result['check'], $result['message']));

            if ($result['hint'] && $result['status'] !== HealthCheck::OK) {
                $this->line('     <fg=gray>→ '.$result['hint'].'</>');
            }
        }

        $this->newLine();

        if ($health->hasFailures($results)) {
            $this->components->error('Some checks failed.');

            return self::FAILURE;
        }

        $this->components->info('PACMS looks healthy.');

        return self::SUCCESS;
    }
}
