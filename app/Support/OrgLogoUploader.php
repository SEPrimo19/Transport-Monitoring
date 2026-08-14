<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Uploads a per-organization branding logo to the PUBLIC `org-logos` Supabase
 * Storage bucket (migration 025) and returns its public URL — which the caller
 * stores in organizations.logo_url. Shared by the org_admin self-service screen
 * (OrgProfileController) and the super_admin Organizations screen
 * (OrganizationController) so the upload path lives in exactly one place.
 *
 * Auth model, consistent with the rest of the app: the upload is performed with
 * the signed-in admin's OWN session token — never service_role. The bucket's RLS
 * (migration 025) only lets an org_admin write its own org's `{orgId}/…` folder;
 * a super_admin may write any. The object is written under `{orgId}/…` so that
 * policy can scope by the first path segment.
 */
class OrgLogoUploader
{
    /**
     * @throws RuntimeException with a user-safe message on any failure; the
     *                          caller should catch it and redirect back with the message so the
     *                          admin's other edits aren't lost.
     */
    public function upload(string $organizationId, UploadedFile $file, string $sessionToken): string
    {
        $base = rtrim((string) config('supabase.url'), '/');
        $anon = (string) config('supabase.anon_key');

        if ($base === '' || $anon === '' || $sessionToken === '') {
            throw new RuntimeException('Storage is not configured, so the logo could not be uploaded.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $object = $organizationId.'/'.time().'_'.Str::random(6).'.'.$ext;
        $endpoint = $base.'/storage/v1/object/org-logos/'.$object;

        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('The selected file could not be read.');
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $anon,
                'Authorization' => 'Bearer '.$sessionToken,
                'x-upsert' => 'true',
            ])
                ->withBody($bytes, $file->getMimeType() ?: 'image/png')
                ->timeout(30)
                ->post($endpoint);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not reach storage to upload the logo. Please try again.');
        }

        if (! $response->successful()) {
            $msg = $response->json('message') ?? $response->json('error') ?? null;
            throw new RuntimeException(is_string($msg) && $msg !== '' ? $msg : 'The logo upload was rejected.');
        }

        // Public bucket → this URL renders in <img> for both the web admin and the
        // mobile app without a token.
        return $base.'/storage/v1/object/public/org-logos/'.$object;
    }

    /**
     * Best-effort delete of a previously uploaded logo object when a logo is
     * removed. Only acts on URLs that point at THIS bucket (a logo_url set via the
     * external "Logo URL" field is left alone). Never throws — clearing
     * organizations.logo_url is what actually matters; an orphaned object is
     * harmless.
     */
    public function deleteIfOwned(?string $url, string $sessionToken): void
    {
        if ($url === null || $url === '' || $sessionToken === '') {
            return;
        }

        $base = rtrim((string) config('supabase.url'), '/');
        $marker = '/storage/v1/object/public/org-logos/';
        $pos = strpos($url, $marker);
        if ($pos === false) {
            return; // not one of our bucket objects (e.g. an external URL)
        }

        $object = substr($url, $pos + strlen($marker));
        if ($object === '') {
            return;
        }

        try {
            Http::withHeaders([
                'apikey' => (string) config('supabase.anon_key'),
                'Authorization' => 'Bearer '.$sessionToken,
            ])->timeout(15)->delete($base.'/storage/v1/object/org-logos/'.$object);
        } catch (\Throwable $e) {
            // best-effort only
        }
    }
}
