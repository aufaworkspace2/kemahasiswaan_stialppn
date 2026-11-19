<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\Form;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\StudyProgram;
use App\Services\RichText;
use App\Services\SectionContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function __construct(private RichText $richText) {}

    public static function shared(): array
    {
        $cached = Cache::remember('portal.public.v2', 60, function (): array {
            $pages = Page::where('status', 'published')->get(['id', 'slug']);
            $services = Service::where('is_active', true)->orderBy('position')->get();
            $menus = MenuItem::where('is_visible', true)->orderBy('position')->orderBy('id')->get()->filter(function (MenuItem $item) use ($pages, $services): bool {
                return match ($item->target_type) {
                    'page' => $pages->contains('id', $item->target_id),'service' => $services->contains('id', $item->target_id), default => true
                };
            })->map(function (MenuItem $item) use ($pages, $services): MenuItem {
                $item->url = match ($item->target_type) {
                    'page' => route('pages.show', $pages->firstWhere('id', $item->target_id)->slug),'service' => route('services.show', $services->firstWhere('id', $item->target_id)->slug),default => $item->url
                };

                return $item;
            });

            return ['services' => $services->toArray(), 'settings' => SiteSetting::pluck('value', 'key')->all(), 'menus' => $menus->values()->toArray()];
        });

        $services = Service::hydrate($cached['services']);
        $sections = PageSection::with(['media', 'mobileMedia'])->where('is_visible', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when(! request()->routeIs('home'), fn ($query) => $query->where('location', '!=', 'home'))
            ->orderBy('position')->orderBy('id')->get();
        $sections = app(SectionContent::class)->prepare($sections, $services, $cached['settings']);
        $sectionPosts = $sections->contains('type', 'news') ? Post::where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->limit(20)->get(['id', 'title', 'slug', 'excerpt', 'kind', 'published_at']) : collect();

        return ['services' => $services, 'settings' => $cached['settings'], 'menus' => MenuItem::hydrate($cached['menus']), 'displaySections' => $sections, 'sectionPosts' => $sectionPosts];
    }

    public function home(): View
    {
        return view('portal.home', self::shared());
    }

    public function service(Service $service): View
    {
        abort_unless($service->is_active, 404);

        return view('portal.service', self::shared() + ['service' => $service, 'descriptionHtml' => $this->richText->render($service->description, $service->description_format), 'forms' => Form::with('category')->whereHas('category', fn ($query) => $query->where('is_active', true))->where('service_id', $service->id)->where('status', 'published')->get()]);
    }

    public function form(Service $service, Form $form): View
    {
        abort_unless($service->is_active && $form->service_id === $service->id && $form->status === 'published', 404);
        $form->load('activeVersion', 'category');

        return view('portal.form', self::shared() + ['service' => $service, 'form' => $form, 'version' => $form->activeVersion, 'programs' => StudyProgram::where('is_active', true)->get(), 'cohorts' => Cohort::where('is_active', true)->orderByDesc('year')->get(), 'application' => null, 'revisionToken' => null]);
    }

    public function posts(Request $request): View
    {
        $posts = Post::where('status', 'published')->where('published_at', '<=', now())->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))->latest('published_at')->paginate(9)->withQueryString();

        return view('portal.posts', self::shared() + compact('posts'));
    }

    public function post(Post $post): View
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);

        return view('portal.content', self::shared() + ['content' => $post, 'bodyHtml' => $this->richText->render($post->body, $post->body_format), 'kind' => 'Berita & Pengumuman']);
    }

    public function page(Page $page): View
    {
        abort_unless($page->status === 'published', 404);

        $page->load(['services' => fn ($query) => $query->where('is_active', true)->orderBy('position')]);

        return view('portal.content', self::shared() + ['relatedServices' => $page->services, 'content' => $page, 'bodyHtml' => $this->richText->render($page->body, $page->body_format), 'kind' => 'Informasi kampus']);
    }
}
