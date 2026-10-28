<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LegalDocumentController extends Controller
{
    public function privacy(): Response
    {
        return $this->document('privacy', 'Privacy policy');
    }

    public function terms(): Response
    {
        return $this->document('terms', 'Terms and cancellation');
    }

    private function document(string $name, string $title): Response
    {
        $path = rtrim((string) config('stage-ready.policies_path'), '/\\').DIRECTORY_SEPARATOR.$name.'.md';
        abort_unless(File::isFile($path) && is_readable($path), 404);
        $content = File::get($path);
        abort_if(trim($content) === '', 404);

        return Inertia::render('LegalDocument', ['title' => $title, 'content' => Str::markdown($content,
            ['html_input' => 'strip', 'allow_unsafe_links' => false])]);
    }
}
