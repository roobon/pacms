<?php

namespace Database\Seeders;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\TestimonialAction;
use App\Enums\WorkflowAction;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaCoverage;
use App\Models\Page;
use App\Models\Term;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Content\ContentService;
use App\Services\Content\ContentTypeService;
use App\Services\Media\MediaService;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;
use App\Services\Settings\SettingsService;
use App\Services\Testimonials\TestimonialModerationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo content for every area, created through the same services the admin uses, so
 * links between items, media usage, revisions, the moderation history and the search
 * index are all consistent. Run with `php artisan pacms:demo --fresh` (local only).
 *
 * Covers: users, categories and tags, media (images, PDFs, a private archive file), team,
 * partners, galleries, programs, projects, events, news, publications, media coverage,
 * testimonials in every moderation state, global blocks (a sidebar and a call to action),
 * pages with blocks (home, about, programmes, get involved), the home page setting and
 * module sidebars. Items are in several workflow states to test filters and permissions.
 */
class DemoSeeder extends Seeder
{
    public const USER_PASSWORD = 'Aurora-demo-2026';

    private User $admin;

    /** @var array<string, Media> */
    private array $media = [];

    /** @var array<string, int> */
    private array $terms = [];

    /** @var array<string, ContentItem> */
    private array $items = [];

    /** @var array<string, User> */
    private array $people = [];

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly ContentService $content,
        private readonly MediaService $mediaService,
        private readonly PageService $pages,
        private readonly PublishingService $publishing,
        private readonly GlobalBlockService $globals,
        private readonly SettingsService $settings,
        private readonly TestimonialModerationService $testimonials,
    ) {}

    public function run(): void
    {
        // Image variants are generated at once instead of waiting for the queue worker.
        config(['queue.default' => 'sync']);
        // The database may just have been wiped in this process (pacms:demo --fresh).
        $this->types->reload();

        $this->admin = User::query()->where('email', 'super-admin@pacms.test')->first()
            ?? User::query()->role('super-admin')->firstOrFail();

        $this->step('Users', fn () => $this->users());
        $this->step('Categories and tags', fn () => $this->taxonomies());
        $this->step('Media library', fn () => $this->mediaLibrary());
        $this->step('Team and partners', fn () => $this->teamAndPartners());
        $this->step('Galleries', fn () => $this->galleries());
        $this->step('Programs and projects', fn () => $this->programsAndProjects());
        $this->step('Events', fn () => $this->events());
        $this->step('Links from galleries', fn () => $this->linkGalleries());
        $this->step('News', fn () => $this->news());
        $this->step('Publications', fn () => $this->publications());
        $this->step('Media coverage', fn () => $this->coverage());
        $this->step('Testimonials', fn () => $this->testimonialSet());
        $this->step('Content types made in the admin', fn () => $this->adminMadeTypes());
        $this->step('Global blocks and sidebars', fn () => $this->globalBlocks());
        $this->step('Pages and home page', fn () => $this->sitePages());
    }

    private function step(string $label, callable $callback): void
    {
        $started = microtime(true);
        $callback();
        $this->command?->line(sprintf('  <info>✓</info> %s <fg=gray>(%.1fs)</>', $label, microtime(true) - $started));
    }

    // --- Users ---------------------------------------------------------------------------

    private function users(): void
    {
        foreach ([
            'rahim' => ['Rahim Uddin', true],
            'nadia' => ['Nadia Islam', true],
            'tanvir' => ['Tanvir Ahmed', true],
            'farzana' => ['Farzana Akter', true],
            'unverified' => ['Sabbir Hossain', false],
        ] as $key => [$name, $verified]) {
            $user = User::query()->firstOrCreate(['email' => "{$key}@demo.pacms.test"], ['name' => $name, 'password' => self::USER_PASSWORD]);
            $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();
            $user->syncRoles(['registered-user']);
            $this->people[$key] = $user;
        }
    }

    // --- Taxonomies ----------------------------------------------------------------------

    private function taxonomies(): void
    {
        $sets = [
            'news_category' => ['Announcements', 'Field stories', 'Schools', 'Climate action'],
            'event_category' => ['Workshops', 'Campaigns', 'Conferences'],
            'department' => ['Leadership', 'Programmes', 'Communications', 'Finance & operations'],
            'partner_category' => ['Government', 'International', 'NGO', 'Corporate'],
            'publication_category' => ['Annual reports', 'Toolkits', 'Policy briefs'],
            'media_coverage_category' => ['Environment', 'Education', 'Interviews'],
            'tag' => ['Mangroves', 'Recycling', 'Youth', 'Water', 'Trees'],
        ];
        foreach ($sets as $taxonomy => $names) {
            foreach ($names as $position => $name) {
                $term = Term::query()->firstOrCreate(
                    ['taxonomy' => $taxonomy, 'slug' => Str::slug($name)],
                    ['name' => $name, 'position' => $position],
                );
                $this->terms["{$taxonomy}:{$name}"] = $term->id;
            }
        }
    }

    /**
     * @return list<int>
     */
    private function term(string $taxonomy, string ...$names): array
    {
        return array_map(fn (string $name) => $this->terms["{$taxonomy}:{$name}"], $names);
    }

    // --- Media ---------------------------------------------------------------------------

    private function mediaLibrary(): void
    {
        $images = [
            'hero' => ['Children planting mangrove seedlings on the coast', [10, 107, 102], 1920, 1080],
            'mangroves' => ['Mangrove forest at low tide', [16, 94, 63], 1600, 1000],
            'classroom' => ['Students sorting waste in a classroom', [37, 99, 158], 1600, 1000],
            'river' => ['Volunteers cleaning a river bank', [14, 116, 144], 1600, 1000],
            'workshop' => ['Teachers at a training workshop', [180, 83, 9], 1600, 1000],
            'trees' => ['Saplings ready for planting', [77, 124, 15], 1600, 1000],
            'reporters' => ['Young reporters interviewing a farmer', [126, 34, 206], 1600, 1000],
            'conference' => ['Panel at the national youth conference', [30, 64, 175], 1600, 1000],
            'beach' => ['Beach clean-up at Cox\'s Bazar', [2, 132, 199], 1600, 1000],
            'garden' => ['School garden with vegetables', [101, 163, 13], 1600, 1000],
            'recycling' => ['Recycling station made by students', [190, 18, 60], 1600, 1000],
            'forest' => ['Learning walk in a forest', [21, 128, 61], 1600, 1000],
        ];
        foreach ($images as $key => [$alt, $rgb, $w, $h]) {
            $this->media[$key] = $this->image("{$key}.jpg", $alt, $rgb, $w, $h);
        }

        // Slider backgrounds (no caption: the slide's headline goes on top).
        foreach (['slide-coast' => ['Coastline with young mangroves', [10, 107, 102]], 'slide-mangroves' => ['Mangrove roots at low tide', [16, 94, 63]], 'slide-school' => ['Students in a school garden', [37, 99, 158]]] as $key => [$alt, $rgb]) {
            $this->media[$key] = $this->image("{$key}.jpg", $alt, $rgb, 1920, 1080, caption: false);
        }

        // Portraits and logos (square).
        $portraits = ['ayesha' => [148, 63, 107], 'karim' => [15, 118, 110], 'mitu' => [194, 65, 12], 'jahid' => [67, 56, 202], 'sumi' => [190, 24, 93], 'arif' => [3, 105, 161]];
        foreach ($portraits as $key => $rgb) {
            $this->media["portrait-{$key}"] = $this->image("portrait-{$key}.jpg", '', $rgb, 800, 800, initials: strtoupper(substr($key, 0, 1)));
        }
        foreach (['dept-env' => 'DoE', 'unesco' => 'UN', 'green-earth' => 'GE', 'brac' => 'BR', 'bank' => 'CB', 'fee' => 'FEE'] as $key => $initials) {
            $this->media["logo-{$key}"] = $this->image("logo-{$key}.png", '', [71, 85, 105], 600, 600, initials: $initials, png: true);
        }

        $this->media['annual-report'] = $this->pdf('annual-report-2025.pdf', 'Annual report 2025');
        $this->media['toolkit'] = $this->pdf('eco-schools-toolkit.pdf', 'Eco-Schools toolkit');
        $this->media['policy-brief'] = $this->pdf('plastic-policy-brief.pdf', 'Plastic policy brief');
        $this->media['project-plan'] = $this->pdf('mangrove-project-plan.pdf', 'Mangrove project plan');
        // A press clipping kept private: shown on its coverage page only because rights are confirmed.
        $this->media['clipping'] = $this->pdf('daily-star-clipping.pdf', 'Press clipping', private: true);
    }

    /**
     * A generated placeholder: a photo-like picture (gradient, soft shapes, caption) or, with
     * initials, a logo or portrait mark.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function image(string $name, string $alt, array $rgb, int $width, int $height, ?string $initials = null, bool $png = false, bool $caption = true): Media
    {
        $image = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = $rgb;
        $logo = $png && $initials !== null;

        if ($logo) {
            // Logo: a coloured circle with the initials on white.
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            $mark = imagecolorallocate($image, ...$this->logoColour($name));
            imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width * .8), (int) ($height * .8), $mark);
        } else {
            for ($y = 0; $y < $height; $y++) {
                // Vertical gradient: lighter at the top.
                $f = $y / $height;
                imageline($image, 0, $y, $width, $y, imagecolorallocate($image, (int) min(255, $r + 70 * (1 - $f)), (int) min(255, $g + 70 * (1 - $f)), (int) min(255, $b + 70 * (1 - $f))));
            }
            $light = imagecolorallocatealpha($image, 255, 255, 255, 105);
            mt_srand(crc32($name));
            for ($i = 0; $i < 6; $i++) {
                $size = mt_rand((int) ($height / 5), (int) ($height / 1.6));
                imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), $size, $size, $light);
            }
        }

        $text = $initials ?? $alt;
        $white = imagecolorallocate($image, 255, 255, 255);
        $font = $this->font();
        if (! $caption) {
            // Slide backgrounds: the slide's own headline goes on top.
        } elseif ($font !== null) {
            $size = $initials !== null ? $width / (strlen($initials) > 2 ? 5.5 : 4) : $width / 34;
            $lines = $initials !== null ? [$initials] : explode("\n", wordwrap($text, 22, "\n"));
            $lineHeight = $size * 1.5;
            $y = ($height - $lineHeight * count($lines)) / 2 + $size;
            foreach ($lines as $line) {
                $box = imagettfbbox($size, 0, $font, $line);
                $x = ($width - ($box[2] - $box[0])) / 2;
                if ($initials === null) {
                    // A soft shadow keeps the caption readable on light shapes.
                    imagettftext($image, $size, 0, (int) $x + 2, (int) $y + 2, imagecolorallocatealpha($image, 0, 0, 0, 80), $font, $line);
                }
                imagettftext($image, $size, 0, (int) $x, (int) $y, $white, $font, $line);
                $y += $lineHeight;
            }
        } else {
            // No TrueType font on this machine: the built-in bitmap font, scaled up.
            $small = imagecreatetruecolor(max(1, imagefontwidth(5) * strlen($text)), imagefontheight(5));
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
            imagesavealpha($small, true);
            imagestring($small, 5, 0, 0, $text, imagecolorallocate($small, 255, 255, 255));
            $scale = (int) max(1, floor(($initials !== null ? $width * .4 : $width * .7) / imagesx($small)));
            $tw = imagesx($small) * $scale;
            $th = imagesy($small) * $scale;
            imagecopyresized($image, $small, (int) (($width - $tw) / 2), (int) (($height - $th) / 2), 0, 0, $tw, $th, imagesx($small), imagesy($small));
        }

        $path = tempnam(sys_get_temp_dir(), 'pacms-demo').($png ? '.png' : '.jpg');
        $png ? imagepng($image, $path) : imagejpeg($image, $path, 85);

        return $this->mediaService->store(new UploadedFile($path, $name, $png ? 'image/png' : 'image/jpeg', null, true), $this->admin, ['alt' => $alt]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function logoColour(string $name): array
    {
        $palette = [[10, 107, 102], [30, 64, 175], [180, 83, 9], [126, 34, 206], [190, 18, 60], [21, 128, 61]];

        return $palette[crc32($name) % count($palette)];
    }

    private function font(): ?string
    {
        foreach (['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/Library/Fonts/Arial Bold.ttf', 'C:\\Windows\\Fonts\\arialbd.ttf'] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function pdf(string $name, string $title, bool $private = false): Media
    {
        // A small valid one-page PDF with the title as text.
        $stream = "BT /F1 24 Tf 72 720 Td ({$title}) Tj ET\nBT /F1 12 Tf 72 690 Td (Demo document generated by PACMS.) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $path = tempnam(sys_get_temp_dir(), 'pacms-demo').'.pdf';
        file_put_contents($path, $pdf);

        return $this->mediaService->store(new UploadedFile($path, $name, 'application/pdf', null, true), $this->admin, ['description' => $title], $private);
    }

    // --- Content helpers -----------------------------------------------------------------

    /**
     * Create an item and take it to a workflow state: published (default), draft,
     * in_review, approved or scheduled.
     *
     * @param  array<string, mixed>  $data
     */
    private function item(string $type, string $key, array $data, string $state = 'published', ?User $author = null): ContentItem
    {
        $definition = $this->types->get($type);
        $item = $this->content->create($definition, $author ?? $this->admin, $data);

        match ($state) {
            'published' => $this->content->transition($definition, $item, WorkflowAction::Publish, $this->admin),
            'in_review' => $this->content->transition($definition, $item, WorkflowAction::Submit, $author ?? $this->admin),
            'approved' => [
                $this->content->transition($definition, $item, WorkflowAction::Submit, $author ?? $this->admin),
                $this->content->transition($definition, $item->fresh(), WorkflowAction::Approve, $this->admin),
            ],
            'scheduled' => $this->content->transition($definition, $item, WorkflowAction::Schedule, $this->admin, ['publish_at' => now()->addDays(3)->setTime(9, 0)]),
            default => null,
        };

        return $this->items["{$type}:{$key}"] = $item->fresh();
    }

    private function id(string $key): int
    {
        return (int) $this->items[$key]->getKey();
    }

    private function mediaId(string $key): int
    {
        return (int) $this->media[$key]->getKey();
    }

    private function date(int $days, string $time = '10:00'): string
    {
        return now()->addDays($days)->format('Y-m-d').'T'.$time;
    }

    // --- Organisation -------------------------------------------------------------------

    private function teamAndPartners(): void
    {
        $team = [
            'ayesha' => ['Ayesha Rahman', 'Executive Director', 'Leadership', 1, true],
            'karim' => ['Karim Hossain', 'Programme Manager, Eco-Schools', 'Programmes', 2, false],
            'mitu' => ['Mitu Chowdhury', 'Project Coordinator, Coastal Resilience', 'Programmes', 3, false],
            'jahid' => ['Jahid Hasan', 'Communications Officer', 'Communications', 4, false],
            'sumi' => ['Sumi Akter', 'Finance Manager', 'Finance & operations', 5, false],
            'arif' => ['Arif Mahmud', 'Youth Engagement Lead', 'Programmes', 6, false],
        ];
        foreach ($team as $key => [$name, $role, $department, $position, $showContact]) {
            $this->item('team', $key, [
                'title' => $name, 'designation' => $role, 'position' => $position,
                'excerpt' => "{$name} is part of the {$department} team.",
                'body' => "<p>{$name} works as {$role}. Before joining, they spent several years in environmental education across Bangladesh.</p>",
                'email' => Str::before(Str::lower($name), ' ').'@example.org', 'show_email' => $showContact,
                'phone' => '+880 1700 00000'.$position, 'show_phone' => $showContact,
                'social_links' => [['network' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/example-'.$key]],
                'featured_media_id' => $this->mediaId("portrait-{$key}"),
                'terms' => $this->term('department', $department),
            ]);
        }
        // An inactive member (not shown on the website).
        $this->item('team', 'former', ['title' => 'Rafiq Islam', 'designation' => 'Former intern', 'position' => 99], 'draft');

        $partners = [
            'dept-env' => ['Department of Environment', 'Government', 'https://doe.example.gov.bd', 1],
            'unesco' => ['UNESCO Dhaka', 'International', 'https://unesco.example.org', 2],
            'green-earth' => ['Green Earth Trust', 'NGO', 'https://greenearth.example.org', 3],
            'brac' => ['BRAC Education', 'NGO', 'https://brac.example.org', 4],
            'bank' => ['Coastal Bank Foundation', 'Corporate', 'https://coastalbank.example.com', 5],
            'fee' => ['Foundation for Environmental Education', 'International', 'https://fee.example.global', 6],
        ];
        foreach ($partners as $key => [$name, $category, $url, $position]) {
            $this->item('partners', $key, [
                'title' => $name, 'website_url' => $url, 'position' => $position, 'featured' => $position <= 3,
                'excerpt' => "{$name} supports our work in schools and communities.",
                'featured_media_id' => $this->mediaId("logo-{$key}"),
                'terms' => $this->term('partner_category', $category),
            ]);
        }
    }

    private function galleries(): void
    {
        $sets = [
            'mangrove-day' => ['Mangrove planting day 2026', ['hero', 'mangroves', 'trees', 'beach'], 'Satkhira', 'Mangroves'],
            'eco-schools' => ['Eco-Schools in action', ['classroom', 'recycling', 'garden', 'workshop'], 'Dhaka', 'Recycling'],
            'youth-conference' => ['National Youth Environment Conference', ['conference', 'reporters', 'forest'], 'Bangla Academy, Dhaka', 'Youth'],
        ];
        foreach ($sets as $key => [$title, $photos, $location, $tag]) {
            $rows = array_map(fn (string $photo) => ['media_id' => $this->mediaId($photo), 'caption' => $this->media[$photo]->alt, 'credit' => 'PACMS demo'], $photos);
            if ($key === 'eco-schools') {
                $rows[] = ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Short film: a day at an Eco-School'];
            }
            $this->item('galleries', $key, [
                'title' => $title, 'gallery_type' => $key === 'eco-schools' ? 'mixed' : 'photo', 'gallery_date' => now()->subMonths(2)->toDateString(),
                'location' => $location, 'credit' => 'Communications team', 'featured_media_id' => $this->mediaId($photos[0]),
                'excerpt' => "Photos from {$title}.", 'gallery_items' => $rows, 'terms' => $this->term('tag', $tag),
            ]);
        }
    }

    private function programsAndProjects(): void
    {
        $this->item('programs', 'eco-schools', [
            'title' => 'Eco-Schools', 'featured' => true, 'featured_media_id' => $this->mediaId('classroom'),
            'excerpt' => 'Students lead their school on a path to sustainability, step by step.',
            'body' => '<p>Eco-Schools is the largest sustainable schools programme in the world. Students form an <strong>Eco-Committee</strong>, review their school, plan actions and share them with the community.</p>',
            'objectives' => [['text' => 'Reduce school waste by half'], ['text' => 'Train 500 teachers a year'], ['text' => 'Give students a voice in decisions']],
            'activities' => [['title' => 'Environmental review', 'text' => 'Students audit energy, water and waste.'], ['title' => 'Action plan', 'text' => 'The Eco-Committee sets targets.'], ['title' => 'Green Flag', 'text' => 'Schools that succeed receive the international award.']],
            'partners' => [$this->id('partners:fee'), $this->id('partners:brac')], 'gallery' => [$this->id('galleries:eco-schools')],
        ]);
        $this->item('programs', 'yre', [
            'title' => 'Young Reporters for the Environment', 'featured_media_id' => $this->mediaId('reporters'),
            'excerpt' => 'Young people investigate environmental issues and report on them in words, photos and video.',
            'body' => '<p>Participants aged 11–25 research a local issue, propose solutions and publish their stories.</p>',
            'objectives' => [['text' => 'Build media skills'], ['text' => 'Spread solutions, not only problems']],
            'partners' => [$this->id('partners:fee'), $this->id('partners:unesco')], 'gallery' => [$this->id('galleries:youth-conference')],
        ]);
        $this->item('programs', 'leaf', [
            'title' => 'Learning about Forests', 'featured_media_id' => $this->mediaId('forest'),
            'excerpt' => 'Outdoor learning that connects students with forests and trees.',
            'objectives' => [['text' => 'Every student plants a tree']],
        ]);

        $projects = [
            'mangroves' => ['Coastal Mangrove Restoration', 'ongoing', -400, null, 'Khulna and Satkhira', 'mitu', ['dept-env', 'bank', 'green-earth'], 'mangrove-day', 'mangroves', true],
            'plastic-free' => ['Plastic-Free Schools', 'ongoing', -200, 300, 'Dhaka and Chattogram', 'karim', ['brac'], null, 'recycling', false],
            'river' => ['Clean Rivers Campaign', 'completed', -900, -100, 'Buriganga river', 'arif', ['green-earth'], null, 'river', false],
            'solar' => ['Solar Classrooms', 'planned', 60, 500, 'Sylhet', 'karim', ['bank'], null, 'workshop', false],
            'gardens' => ['School Kitchen Gardens', 'paused', -300, null, 'Rangpur', 'mitu', [], null, 'garden', false],
        ];
        foreach ($projects as $key => [$title, $status, $start, $end, $location, $manager, $partners, $gallery, $image, $docs]) {
            $this->item('projects', $key, array_filter([
                'title' => $title, 'project_status' => $status, 'location' => $location, 'featured' => $key === 'mangroves',
                'start_date' => now()->addDays($start)->toDateString(), 'end_date' => $end === null ? null : now()->addDays($end)->toDateString(),
                'featured_media_id' => $this->mediaId($image),
                'excerpt' => "{$title}: a project in {$location}.",
                'body' => "<p>{$title} brings schools, families and local government together in {$location}.</p>",
                'manager' => [$this->id("team:{$manager}")],
                'partners' => array_map(fn (string $p) => $this->id("partners:{$p}"), $partners),
                'gallery' => $gallery ? [$this->id("galleries:{$gallery}")] : [],
                'documents' => $docs ? [['media_id' => $this->mediaId('project-plan'), 'label' => 'Project plan (PDF)'], ['media_id' => $this->mediaId('annual-report'), 'label' => 'Annual report 2025']] : [],
            ], fn ($value) => $value !== null));
        }
        // A draft project, not public yet.
        $this->item('projects', 'draft', ['title' => 'Urban Tree Census (idea)', 'project_status' => 'planned'], 'draft');
    }

    private function events(): void
    {
        $events = [
            'teacher-training' => ['Eco-Schools teacher training', 14, '09:30', 'Workshops', 'BRAC Learning Centre, Dhaka', 'workshop', true],
            'mangrove-day' => ['Mangrove Planting Day 2027', 40, '07:00', 'Campaigns', 'Shyamnagar, Satkhira', 'mangroves', false],
            'youth-conference' => ['National Youth Environment Conference', 75, '10:00', 'Conferences', 'Bangla Academy, Dhaka', 'conference', false],
            'beach-cleanup' => ['Cox\'s Bazar beach clean-up', -30, '08:00', 'Campaigns', 'Laboni Beach, Cox\'s Bazar', 'beach', false],
            'reporters-camp' => ['Young Reporters photo camp', -60, '10:00', 'Workshops', 'Sreemangal', 'reporters', false],
            'earth-day' => ['Earth Day school fair', -170, '11:00', 'Campaigns', 'Dhaka Model School', 'garden', false],
        ];
        foreach ($events as $key => [$title, $days, $time, $category, $venue, $image, $featured]) {
            $this->item('events', $key, [
                'title' => $title, 'featured' => $featured, 'featured_media_id' => $this->mediaId($image),
                'start_at' => $this->date($days, $time), 'end_at' => $this->date($days, '16:00'), 'timezone' => 'Asia/Dhaka',
                'venue' => $venue, 'address' => "{$venue}, Bangladesh", 'organizer' => 'Probha Aurora',
                'registration_url' => $days > 0 ? 'https://forms.example.org/'.$key : null,
                'excerpt' => "Join us: {$title}.", 'body' => "<p>{$title} at {$venue}. Bring water, a hat and friends.</p>",
                'terms' => $this->term('event_category', $category),
            ]);
        }
        $this->item('events', 'draft', ['title' => 'Winter camp (planning)', 'start_at' => $this->date(200), 'timezone' => 'Asia/Dhaka'], 'draft');
    }

    /**
     * Galleries link back to the event, project or program they belong to.
     */
    private function linkGalleries(): void
    {
        $definition = $this->types->get('galleries');
        foreach ([
            'mangrove-day' => ['project' => 'projects:mangroves', 'event' => 'events:beach-cleanup'],
            'eco-schools' => ['program' => 'programs:eco-schools'],
            'youth-conference' => ['program' => 'programs:yre', 'event' => 'events:reporters-camp'],
        ] as $key => $links) {
            $gallery = $this->items["galleries:{$key}"]->fresh();
            $data = ['title' => $gallery->title];
            foreach ($links as $field => $target) {
                $data[$field] = [$this->id($target)];
            }
            $this->items["galleries:{$key}"] = $this->content->update($definition, $this->admin, $gallery, $data, (int) $gallery->getAttribute('lock_version'));
        }
    }

    // --- Editorial -----------------------------------------------------------------------

    private function news(): void
    {
        $author = User::query()->where('email', 'author@pacms.test')->first() ?? $this->admin;
        $stories = [
            ['10,000 mangroves planted in Satkhira', 'Field stories', 'mangroves', true, 'Mangroves'],
            ['Eco-Schools reaches 300 schools', 'Announcements', 'classroom', true, 'Recycling'],
            ['Students build a recycling station from scrap', 'Schools', 'recycling', false, 'Recycling'],
            ['Young reporters win national award', 'Announcements', 'reporters', false, 'Youth'],
            ['Why kitchen gardens matter for nutrition', 'Field stories', 'garden', false, 'Trees'],
            ['River clean-up removes two tonnes of plastic', 'Climate action', 'river', false, 'Water'],
        ];
        foreach ($stories as $i => [$title, $category, $image, $featured]) {
            $this->item('news', Str::slug($title), [
                'title' => $title, 'featured' => $featured, 'featured_media_id' => $this->mediaId($image),
                'excerpt' => 'A short summary of the story, shown on cards and in search results.',
                'body' => '<p>'.$title.'. This demo article has a few paragraphs so the page looks real.</p><h2>What happened</h2><p>Students, teachers and families worked together over several weeks.</p><ul><li>Planning with the community</li><li>Training volunteers</li><li>Sharing results</li></ul><blockquote><p>“We want our children to grow up with these forests.” — a parent</p></blockquote>',
                'terms' => $this->term('news_category', $category),
            ]);
        }
        // Workflow states for testing the editorial process.
        $this->item('news', 'draft', ['title' => 'Draft: interview with a head teacher', 'terms' => $this->term('news_category', 'Schools')], 'draft', $author);
        $this->item('news', 'review', ['title' => 'Waiting for review: monsoon tree planting tips', 'terms' => $this->term('news_category', 'Field stories')], 'in_review', $author);
        $this->item('news', 'scheduled', ['title' => 'Scheduled: World Environment Day plans', 'featured_media_id' => $this->mediaId('trees'), 'terms' => $this->term('news_category', 'Announcements')], 'scheduled');
    }

    private function publications(): void
    {
        $this->item('publications', 'annual-report', [
            'title' => 'Annual Report 2025', 'publication_date' => '2026-03-15', 'author_text' => 'Probha Aurora', 'featured' => true,
            'document_media_id' => $this->mediaId('annual-report'), 'featured_media_id' => $this->mediaId('hero'),
            'excerpt' => 'Our year in numbers and stories.', 'terms' => $this->term('publication_category', 'Annual reports'),
        ]);
        $this->item('publications', 'toolkit', [
            'title' => 'Eco-Schools Toolkit for Teachers', 'publication_date' => '2025-11-01', 'author_text' => 'Karim Hossain',
            'document_media_id' => $this->mediaId('toolkit'), 'featured_media_id' => $this->mediaId('classroom'),
            'excerpt' => 'Step-by-step guidance for starting an Eco-Committee.', 'terms' => $this->term('publication_category', 'Toolkits'),
        ]);
        $this->item('publications', 'policy-brief', [
            'title' => 'Plastic in Schools: Policy Brief', 'publication_date' => '2026-06-05', 'author_text' => 'Research team',
            'document_media_id' => $this->mediaId('policy-brief'), 'external_url' => 'https://example.org/policy-brief',
            'excerpt' => 'Recommendations for a national school plastics policy.', 'terms' => $this->term('publication_category', 'Policy briefs'),
        ]);
        $this->item('publications', 'online-only', [
            'title' => 'Mangrove Field Guide (online)', 'publication_date' => '2024-09-10', 'external_url' => 'https://example.org/field-guide',
            'excerpt' => 'Hosted by our partner.', 'terms' => $this->term('publication_category', 'Toolkits'),
        ]);
    }

    private function coverage(): void
    {
        $base = ['availability_override' => 'auto'];
        $this->item('media_coverage', 'daily-star', $base + [
            'title' => 'Students restore mangroves along the Sundarbans edge', 'source_name' => 'The Daily Star', 'source_url' => 'https://www.example.com/daily-star/mangroves',
            'coverage_type' => 'newspaper', 'publication_date' => now()->subDays(20)->toDateString(), 'featured' => true, 'featured_media_id' => $this->mediaId('mangroves'),
            'archive_pdf_media_id' => $this->mediaId('clipping'), 'archive_rights_confirmed' => true, 'archive_rights_note' => 'Permission by e-mail from the features editor (demo).',
            'program' => [$this->id('programs:eco-schools')], 'project' => [$this->id('projects:mangroves')],
            'excerpt' => 'A feature on the coastal restoration project.', 'terms' => $this->term('media_coverage_category', 'Environment'),
        ]);
        $this->item('media_coverage', 'channel-i', $base + [
            'title' => 'Eco-Schools on the evening news', 'source_name' => 'Channel i', 'source_url' => 'https://www.example.com/channel-i/eco-schools',
            'coverage_type' => 'tv', 'publication_date' => now()->subDays(45)->toDateString(), 'featured_media_id' => $this->mediaId('classroom'),
            'program' => [$this->id('programs:eco-schools')], 'terms' => $this->term('media_coverage_category', 'Education'),
        ]);
        $radio = $this->item('media_coverage', 'radio', $base + [
            'title' => 'Interview: youth and climate action', 'source_name' => 'Radio Foorti', 'source_url' => 'https://www.example.com/radio/interview-gone',
            'coverage_type' => 'radio', 'publication_date' => now()->subMonths(8)->toDateString(),
            'program' => [$this->id('programs:yre')], 'terms' => $this->term('media_coverage_category', 'Interviews'),
        ]);
        // The original of this one no longer exists: the page shows a notice.
        $radio->forceFill(['availability' => 'unavailable', 'consecutive_failures' => 3, 'http_status' => 404, 'last_check_error' => 'The server answered with HTTP 404.', 'last_checked_at' => now()->subHours(5)])->saveQuietly();

        $this->item('media_coverage', 'online', $base + [
            'title' => 'Ten schools leading on recycling', 'source_name' => 'bdnews24', 'source_url' => 'https://www.example.com/bdnews/recycling',
            'coverage_type' => 'online', 'publication_date' => now()->subDays(5)->toDateString(), 'featured_media_id' => $this->mediaId('recycling'),
            'project' => [$this->id('projects:plastic-free')], 'terms' => $this->term('media_coverage_category', 'Education'),
        ]);
        $this->item('media_coverage', 'draft', $base + ['title' => 'Magazine feature (awaiting copy)', 'source_name' => 'Bichitra', 'coverage_type' => 'magazine'], 'draft');
        MediaCoverage::query()->whereNotNull('source_url')->whereNull('next_check_at')->update(['next_check_at' => now()->addHours(12)]);
    }

    private function testimonialSet(): void
    {
        $moderator = User::query()->where('email', 'moderator@pacms.test')->first() ?? $this->admin;
        $submit = function (string $person, string $body, array $extra = [], bool $photo = false) {
            $user = $this->people[$person];
            $file = null;
            if ($photo) {
                $portrait = $this->media['portrait-sumi'];
                $copy = tempnam(sys_get_temp_dir(), 'pacms-demo').'.jpg';
                imagejpeg(imagecreatefromstring((string) Storage::disk($portrait->disk)->get($portrait->path)) ?: imagecreatetruecolor(400, 400), $copy);
                $file = new UploadedFile($copy, 'me.jpg', 'image/jpeg', null, true);
            }

            return $this->testimonials->submit($user, ['name' => $user->name, 'body' => $body] + $extra, $file, '203.0.113.'.random_int(1, 254));
        };
        $step = fn (Testimonial $t, TestimonialAction ...$actions) => array_map(fn (TestimonialAction $a) => $this->testimonials->transition($t, $a, $moderator, $a === TestimonialAction::Reject ? 'Promotes a private tutoring business (demo reason).' : null), $actions);
        $publish = [TestimonialAction::StartReview, TestimonialAction::Approve, TestimonialAction::Publish];

        $t = $submit('rahim', 'Since our school joined Eco-Schools, students sort their waste without being told. The Eco-Committee even convinced the canteen to stop using plastic plates.', ['organization' => 'Dhaka Model School', 'designation' => 'Science teacher', 'program_id' => $this->id('programs:eco-schools')], photo: true);
        $step($t, ...$publish);
        $t->forceFill(['featured' => true, 'position' => 1, 'rating' => 5])->save();

        $t = $submit('nadia', 'Writing about the river near my home taught me more than any textbook. Now people in my neighbourhood talk about the factory waste.', ['organization' => 'Young Reporters, Khulna', 'designation' => 'Student reporter', 'program_id' => $this->id('programs:yre')]);
        $step($t, ...$publish);
        $t->forceFill(['position' => 2, 'rating' => 5, 'event_id' => $this->id('events:reporters-camp')])->save();

        $t = $submit('tanvir', 'Planting mangroves with my children was the best day of the year. The trees we planted last monsoon are already taller than my son.', ['organization' => 'Shyamnagar community', 'designation' => 'Parent', 'project_id' => $this->id('projects:mangroves')]);
        $step($t, ...$publish);
        $t->forceFill(['position' => 3, 'rating' => 4])->save();

        // Waiting in the moderation queue.
        $submit('farzana', 'The teacher training was practical and fun. I would love a follow-up session on school gardens.', ['organization' => 'Rangpur Girls School', 'designation' => 'Head teacher']);
        $t = $submit('rahim', 'Our second year was even better: we won the Green Flag!', ['organization' => 'Dhaka Model School']);
        $step($t, TestimonialAction::StartReview);
        // Approved but not published yet.
        $t = $submit('tanvir', 'The beach clean-up showed my students how much plastic ends up in the sea.', ['organization' => 'Cox\'s Bazar High School', 'designation' => 'Teacher']);
        $step($t, TestimonialAction::Approve);
        // Rejected (with an internal reason).
        $t = $submit('nadia', 'Great programme! Also, book private tuition with me at a discount.');
        $step($t, TestimonialAction::Reject);

        // Entered by staff from a letter, published.
        $staff = $this->testimonials->create($this->admin, [
            'name' => 'Dr. Selina Haque', 'designation' => 'Professor of Environmental Science', 'organization' => 'University of Dhaka',
            'body' => 'Probha Aurora has built one of the most effective school-based environmental programmes in the region.',
            'citation' => 'Letter to the board, 2026', 'testimonial_date' => '2026-02-10', 'rating' => 5, 'featured' => true, 'position' => 0,
        ]);
        $this->testimonials->transition($staff, TestimonialAction::Approve, $this->admin);
        $this->testimonials->transition($staff, TestimonialAction::Publish, $this->admin);
    }

    // --- Content types made in the admin (Phase 8D) --------------------------------------

    private function adminMadeTypes(): void
    {
        app(ContentTypeService::class)->create($this->admin, [
            'label' => 'Success stories', 'singular' => 'success story', 'icon' => 'bi-trophy', 'workflow' => 'editorial',
            'fields' => [
                ['key' => 'school', 'type' => 'text', 'label' => 'School', 'required' => true],
                ['key' => 'district', 'type' => 'select', 'label' => 'District', 'options' => ['dhaka' => 'Dhaka', 'khulna' => 'Khulna', 'satkhira' => 'Satkhira', 'sylhet' => 'Sylhet']],
                ['key' => 'year', 'type' => 'number', 'label' => 'Year'],
                ['key' => 'themes', 'type' => 'multi-select', 'label' => 'Themes', 'options' => ['waste' => 'Waste', 'water' => 'Water', 'trees' => 'Trees', 'energy' => 'Energy']],
                ['key' => 'story', 'type' => 'rich-text', 'label' => 'The story'],
                ['key' => 'milestones', 'type' => 'repeater', 'label' => 'Milestones', 'fields' => [['key' => 'when', 'type' => 'text', 'label' => 'When'], ['key' => 'what', 'type' => 'textarea', 'label' => 'What']]],
                ['key' => 'contact_note', 'type' => 'textarea', 'label' => 'Internal note'],
            ],
            'display' => ['contact_note' => 'hidden'],
        ]);
        $stories = [
            ['Green Flag for Dhaka Model School', 'Dhaka Model School', 'dhaka', 2025, ['waste', 'water'], 'classroom'],
            ['A mangrove nursery run by students', 'Shyamnagar High School', 'satkhira', 2024, ['trees'], 'mangroves'],
            ['Solar lamps for evening study', 'Sreemangal Girls School', 'sylhet', 2026, ['energy'], 'workshop'],
        ];
        foreach ($stories as [$title, $school, $district, $year, $themes, $image]) {
            $this->item('success_stories', Str::slug($title), [
                'title' => $title, 'featured_media_id' => $this->mediaId($image), 'excerpt' => "How {$school} did it.",
                'school' => $school, 'district' => $district, 'year' => $year, 'themes' => $themes,
                'story' => "<p>The Eco-Committee of {$school} set a goal, involved families and kept going for two years.</p>",
                'milestones' => [['when' => (string) ($year - 2), 'what' => 'Eco-Committee formed'], ['when' => (string) $year, 'what' => 'Goal reached']],
                'contact_note' => 'Photos approved by the head teacher.',
            ]);
        }

        // A simple active / inactive type without a listing page (items shown in blocks only).
        app(ContentTypeService::class)->create($this->admin, [
            'label' => 'Board members', 'singular' => 'board member', 'icon' => 'bi-person-badge', 'workflow' => 'managed', 'has_archive' => false,
            'fields' => [['key' => 'role', 'type' => 'text', 'label' => 'Role on the board'], ['key' => 'term', 'type' => 'text', 'label' => 'Term']],
        ]);
        foreach ([['Prof. Nazma Begum', 'Chair', 1], ['Mr. Rashed Karim', 'Treasurer', 2]] as [$name, $role, $position]) {
            $this->item('board_members', Str::slug($name), ['title' => $name, 'role' => $role, 'term' => '2025–2028', 'position' => $position]);
        }
    }

    // --- Global blocks, pages and settings ---------------------------------------------

    private function globalBlocks(): void
    {
        $sidebar = $this->globals->create($this->admin, [
            'name' => 'News sidebar', 'kind' => 'sidebar',
            'blocks' => [
                ['type' => 'heading', 'content' => ['text' => 'Stay in touch', 'level' => '3']],
                ['type' => 'rich-text', 'content' => ['html' => '<p>Get our monthly newsletter with stories from schools and the field.</p>']],
                ['type' => 'button', 'content' => ['label' => 'Get involved', 'link' => ['type' => 'url', 'url' => '/get-involved']]],
                ['type' => 'events', 'content' => ['heading' => 'Upcoming events', 'show_excerpt' => false], 'source' => ['mode' => 'dynamic', 'entity' => 'events', 'order' => 'soonest', 'limit' => 3, 'filters' => ['when' => 'upcoming']], 'display' => ['mode' => 'list', 'show_image' => false]],
            ],
        ]);
        $this->globals->publish($this->admin, $sidebar);

        $cta = $this->globals->create($this->admin, [
            'name' => 'Call to action: volunteer', 'kind' => 'generic',
            'blocks' => [[
                'type' => 'cta',
                'layout' => ['padding' => ['top' => ['$token' => 'space.7'], 'bottom' => ['$token' => 'space.7']], 'text_align' => 'center'],
                'style' => ['background' => ['type' => 'color', 'color' => ['$token' => 'color.primary']], 'radius' => ['$token' => 'radius.lg']],
                'children' => [
                    ['type' => 'heading', 'content' => ['text' => 'Plant the future with us', 'level' => '2']],
                    ['type' => 'rich-text', 'content' => ['html' => '<p>Volunteer at a planting day, bring Eco-Schools to your school, or support a project.</p>']],
                    ['type' => 'button', 'content' => ['label' => 'Get involved', 'variant' => 'accent', 'link' => ['type' => 'url', 'url' => '/get-involved']]],
                ],
            ]],
        ]);
        $this->globals->publish($this->admin, $cta);
        $this->ctaId = $cta->id;

        $sidebars = [];
        foreach (array_keys($this->types->all()) as $key) {
            $sidebars[$key] = ['global_block_id' => in_array($key, ['news', 'events'], true) ? $sidebar->id : null, 'position' => 'right'];
        }
        $this->settings->set('content', ['sidebars' => $sidebars], $this->admin);
    }

    private int $ctaId = 0;

    private function sitePages(): void
    {
        $section = fn (array $children, array $extra = []) => ['type' => 'section', 'children' => $children] + $extra;
        // Collection blocks start from the block's own defaults, as when inserted in the builder.
        $dynamic = function (string $type, string $entity, array $source = [], array $content = [], array $display = []) {
            $defaults = app(BlockRegistry::class)->get($type)->defaults();

            return [
                'type' => $type,
                'content' => $content + (array) ($defaults['content'] ?? []),
                'source' => ['mode' => 'dynamic', 'entity' => $entity] + $source + (array) ($defaults['source'] ?? []),
                'display' => $display + (array) ($defaults['display'] ?? []),
            ];
        };

        $home = $this->page('Home', 'home', 'Environmental education for every school in Bangladesh.', [
            [
                'type' => 'slider',
                'content' => ['aria_label' => 'Highlights', 'height' => 'medium', 'autoplay' => true, 'interval' => 7, 'show_arrows' => true, 'show_dots' => true],
                'children' => [
                    $this->slide('slide-coast', 'Probha Aurora', 'Young people restoring Bangladesh’s environment', 'We bring environmental education to schools and communities, from the mangrove coast to the tea gardens.', [
                        ['Our programmes', '/our-programmes', 'accent'], ['Get involved', '/get-involved', 'outline'],
                    ], level: '1'),
                    $this->slide('slide-mangroves', 'Coastal Mangrove Restoration', '125,000 mangroves planted along the coast', 'Students and families protect their villages from storms, one seedling at a time.', [
                        ['See the project', '/projects/coastal-mangrove-restoration', 'accent'],
                    ]),
                    $this->slide('slide-school', 'Eco-Schools', '312 schools on the path to a Green Flag', 'Eco-Committees cut waste, save water and lead their communities.', [
                        ['Bring Eco-Schools to your school', '/programs/eco-schools', 'accent'],
                    ], align: 'center'),
                ],
            ],
            $section([['type' => 'statistics', 'content' => ['heading' => 'Our impact', 'items' => [
                ['value' => 312, 'label' => 'Eco-Schools', 'icon' => 'bi-building'],
                ['value' => 48000, 'suffix' => '+', 'label' => 'Students reached', 'icon' => 'bi-people'],
                ['value' => 125000, 'label' => 'Mangroves planted', 'icon' => 'bi-tree'],
                ['value' => 64, 'label' => 'Districts', 'icon' => 'bi-geo-alt'],
            ]]]]),
            $section([$dynamic('news', 'news', ['order' => 'latest', 'limit' => 3], ['heading' => 'Latest news'])]),
            $section([['type' => 'columns', 'layout' => ['columns' => ['desktop' => [7, 5], 'mobile' => [12, 12]]], 'children' => [
                ['type' => 'column', 'children' => [$dynamic('programs', 'programs', ['order' => 'latest', 'limit' => 3], ['heading' => 'Programmes', 'show_date' => false], ['mode' => 'list'])]],
                ['type' => 'column', 'children' => [$dynamic('events', 'events', ['order' => 'soonest', 'limit' => 3, 'filters' => ['when' => 'upcoming']], ['heading' => 'Upcoming events', 'show_excerpt' => false], ['mode' => 'list', 'show_image' => false])]],
            ]]]),
            $section([$dynamic('testimonials', 'testimonials', ['order' => 'position', 'limit' => 6], ['heading' => 'What people say'], ['mode' => 'quote-slider'])]),
            $section([$dynamic('media-coverage', 'media_coverage', ['order' => 'latest', 'limit' => 3], ['heading' => 'In the media'])]),
            $section([$dynamic('partners', 'partners', ['order' => 'position', 'limit' => 12], ['heading' => 'Our partners', 'style' => 'logos', 'grayscale' => true])]),
            ['type' => 'global-ref', 'global_block_id' => $this->ctaId],
        ]);

        $about = $this->page('About us', 'about', 'Who we are and how we work.', [
            $section([
                ['type' => 'heading', 'content' => ['text' => 'Our story', 'level' => '2']],
                ['type' => 'rich-text', 'content' => ['html' => '<p>Founded in 2010 by teachers in Khulna, Probha Aurora now works with schools in 64 districts.</p><p>We believe young people are the strongest voice for the environment.</p>']],
            ]),
            $section([$dynamic('team', 'team', ['order' => 'position', 'limit' => 12], ['heading' => 'Our team'])]),
            $section([$dynamic('partners', 'partners', ['order' => 'position', 'limit' => 12], ['heading' => 'Partners', 'style' => 'cards'])]),
            $section([$dynamic('gallery', 'galleries', ['filters' => ['gallery' => $this->id('galleries:mangrove-day')]], ['heading' => 'In pictures'])]),
        ]);
        $this->page('Our history', 'history', 'From one school in Khulna to 300 across the country.', [
            $section([['type' => 'rich-text', 'content' => ['html' => '<p>2010: the first Eco-Committee. 2015: Young Reporters starts. 2022: coastal mangrove restoration begins.</p>']]]),
        ], parent: $about);

        $this->page('Our programmes', 'our-programmes', 'Long-term programmes and the projects that bring them to life.', [
            $section([$dynamic('programs', 'programs', ['order' => 'latest', 'limit' => 6], ['heading' => 'Programmes', 'show_date' => false])]),
            $section([$dynamic('projects', 'projects', ['order' => 'latest', 'limit' => 6, 'filters' => ['project_status' => 'ongoing']], ['heading' => 'Ongoing projects'], ['mode' => 'featured'])]),
            $section([$dynamic('publications', 'publications', ['order' => 'latest', 'limit' => 3], ['heading' => 'Publications'])]),
            $section([$dynamic('galleries', 'galleries', ['order' => 'latest', 'limit' => 3], ['heading' => 'Galleries'])]),
        ]);

        $this->page('Get involved', 'get-involved', 'Volunteer, bring a programme to your school, or partner with us.', [
            $section([
                ['type' => 'cards', 'content' => ['heading' => 'Ways to help', 'items' => [
                    ['icon' => 'bi-tree', 'title' => 'Volunteer', 'text' => 'Join a planting day or a clean-up.', 'link' => ['type' => 'url', 'url' => '/events']],
                    ['icon' => 'bi-building', 'title' => 'Register your school', 'text' => 'Start Eco-Schools with our toolkit.', 'link' => ['type' => 'url', 'url' => '/publications']],
                    ['icon' => 'bi-chat-quote', 'title' => 'Share your story', 'text' => 'Create an account and send a testimonial.', 'link' => ['type' => 'url', 'url' => '/account/register']],
                ]]],
            ]),
            $section([['type' => 'faq', 'content' => ['heading' => 'Questions', 'items' => [
                ['question' => 'Does it cost anything for schools?', 'answer' => '<p>No. The programme is free for public schools.</p>'],
                ['question' => 'Can I volunteer if I am under 18?', 'answer' => '<p>Yes, with a parent or teacher.</p>'],
            ]]]]),
            ['type' => 'global-ref', 'global_block_id' => $this->ctaId],
        ]);

        // A draft page, not public.
        $this->pages->create($this->admin, ['title' => 'Careers (draft)', 'template' => 'default', 'excerpt' => 'Open positions.']);

        $this->settings->set('site', [
            'name' => 'Probha Aurora',
            'tagline' => 'Environmental education for every school',
            'description' => 'Probha Aurora brings environmental education to schools and communities across Bangladesh.',
            'contact_email' => 'hello@example.org',
            'contact_phone' => '+880 2 0000 0000',
            'address' => 'House 12, Road 5, Dhanmondi, Dhaka 1205',
            'homepage_page_id' => $home->id,
        ], $this->admin);
    }

    /**
     * One slide of the home page slider: a photo from the library with an eyebrow, headline,
     * text and buttons ([label, url, variant]).
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $buttons
     * @return array<string, mixed>
     */
    private function slide(string $image, string $eyebrow, string $headline, string $text, array $buttons, string $level = '2', string $align = 'start'): array
    {
        return [
            'type' => 'slide',
            'content' => ['image' => ['$media' => $this->mediaId($image)], 'shade' => 'dark', 'align' => $align],
            'children' => [
                ['type' => 'heading', 'content' => ['eyebrow' => $eyebrow, 'text' => $headline, 'level' => $level]],
                ['type' => 'rich-text', 'content' => ['html' => "<p>{$text}</p>"]],
                ['type' => 'button-group', 'children' => array_map(fn (array $button) => [
                    'type' => 'button', 'content' => ['label' => $button[0], 'variant' => $button[2], 'link' => ['type' => 'url', 'url' => $button[1]]],
                ], $buttons)],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(string $title, string $slug, string $excerpt, array $blocks, ?Page $parent = null): Page
    {
        $page = $this->pages->create($this->admin, array_filter([
            'title' => $title, 'slug' => $slug, 'template' => 'default', 'excerpt' => $excerpt, 'blocks' => $blocks, 'parent_id' => $parent?->id,
        ], fn ($value) => $value !== null));

        return $this->publishing->transition($page->fresh(), WorkflowAction::Publish, $this->admin)->fresh();
    }
}
