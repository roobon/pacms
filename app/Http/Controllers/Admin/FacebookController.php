<?php

namespace App\Http\Controllers\Admin;

use App\Cms\External\Facebook\GraphClient;
use App\Cms\External\FeedException;
use App\Http\Controllers\Controller;
use App\Services\External\FacebookConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Design → External sources → Connect Facebook (permission "Connect Facebook").
 */
class FacebookController extends Controller
{
    public function __construct(private readonly FacebookConnectService $facebook) {}

    public function show(Request $request): View
    {
        return view('admin.external-sources.facebook', [
            'appConfigured' => $this->facebook->appConfigured(),
            // Pages offered after "Connect with Facebook" (names only; tokens stay in the session).
            'pages' => array_map(fn ($page) => ['id' => $page['id'], 'name' => $page['name']], (array) $request->session()->get('facebook.pages', [])),
            'callback' => route('admin.facebook.callback'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless($this->facebook->appConfigured(), 404);
        $state = Str::random(40);
        $request->session()->put('facebook.state', $state);

        return redirect()->away(GraphClient::dialogUrl(route('admin.facebook.callback'), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->session()->pull('facebook.state', '');
        if ($state === '' || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('admin.facebook.show')->with('warning', __('The Facebook answer could not be matched to your request. Please try again.'));
        }
        if ($request->filled('error')) {
            return redirect()->route('admin.facebook.show')->with('warning', __('Facebook did not connect: the permission was not granted.'));
        }

        try {
            $pages = $this->facebook->pagesFromCode((string) $request->query('code'), route('admin.facebook.callback'));
        } catch (FeedException $e) {
            return redirect()->route('admin.facebook.show')->with('warning', $e->getMessage());
        }
        $request->session()->put('facebook.pages', $pages);

        return redirect()->route('admin.facebook.show')->with('success', __('Choose the Page to show on the website.'));
    }

    public function choose(Request $request): RedirectResponse
    {
        $data = $request->validate(['page_id' => ['required', 'string', 'max:64']]);
        $page = collect((array) $request->session()->get('facebook.pages', []))->firstWhere('id', $data['page_id']);
        abort_if($page === null, 422, 'Unknown Page.');

        return $this->connect($request, (string) $page['id'], (string) $page['token']);
    }

    public function manual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'page_id' => ['required', 'string', 'regex:/^[0-9]{5,30}$/'],
            'page_token' => ['required', 'string', 'min:20', 'max:1024'],
        ], ['page_id.regex' => __('The Page ID is a number (Page settings → About → Page ID).')], ['page_id' => 'Page ID', 'page_token' => 'Page access token']);

        return $this->connect($request, $data['page_id'], trim($data['page_token']));
    }

    private function connect(Request $request, string $pageId, string $token): RedirectResponse
    {
        try {
            $source = $this->facebook->connect($request->user(), $pageId, $token);
        } catch (FeedException $e) {
            return back()->withInput($request->except('page_token'))->with('warning', $e->getMessage());
        }
        $request->session()->forget('facebook.pages');

        return redirect()->route('admin.external-sources.edit', $source)->with($source->last_status === 'ok' ? 'success' : 'warning', $source->last_status === 'ok'
            ? __('Facebook Page connected with :count posts. Show them with a "Facebook posts" block.', ['count' => $source->items()->count()])
            : __('The Page is connected, but reading its posts failed: :error', ['error' => $source->last_error]));
    }
}
