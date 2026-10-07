<?php

namespace App\Console\Commands;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-creates responsive image variants (CMS-ARCHITECTURE.md §14), e.g. after changing
 * pacms.media.variant_widths or when variants are missing after a failed queue job.
 */
class RegenerateMediaCommand extends Command
{
    protected $signature = 'pacms:media:regenerate
        {ids?* : Only these media ids}
        {--missing : Only images that have no variants yet}';

    protected $description = 'Regenerate responsive image variants';

    public function handle(MediaService $media): int
    {
        $query = Media::query()
            ->where('kind', MediaKind::Image)
            ->where('extension', '!=', 'svg')
            ->when($this->argument('ids'), fn ($q, $ids) => $q->whereKey($ids))
            ->when($this->option('missing'), fn ($q) => $q->whereNull('variants'));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->components->info('Nothing to regenerate.');

            return self::SUCCESS;
        }

        $failed = 0;
        $bar = $this->output->createProgressBar($total);
        $query->orderBy('id')->each(function (Media $item) use ($media, $bar, &$failed) {
            try {
                $media->generateVariants($item);
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->components->warn("#{$item->id} {$item->original_name}: {$e->getMessage()}");
            }
            $bar->advance();
        });
        $bar->finish();
        $this->newLine();

        $this->components->info(($total - $failed)." of {$total} images regenerated.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
