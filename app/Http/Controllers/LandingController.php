<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public pages: the landing page (/) and the privacy page (/privacy).
 *
 * Both are open to everyone and read no database, so they load even when Supabase is
 * slow. The only thing taken from the visitor is whether an admin session already
 * exists, so the sign-in button can say "Open dashboard" instead.
 */
class LandingController extends Controller
{
    public function index(Request $request): View
    {
        return view('landing', $this->shared($request));
    }

    public function privacy(Request $request): View
    {
        return view('privacy', $this->shared($request));
    }

    /** @return array<string, mixed> */
    private function shared(Request $request): array
    {
        return [
            // Presence of the token only; the dashboard itself verifies it, so an
            // expired token just sends the visitor on to the sign-in page.
            'hasAdminSession' => $request->session()->has('supabase_token'),
            'app' => config('landing'),
        ];
    }
}
