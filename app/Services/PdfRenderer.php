<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Quotation, tax-invoice and receipt PDFs. Noto Sans is embedded so ₹
 * prints; images are inlined as data URIs so nothing is fetched remotely.
 */
class PdfRenderer
{
    public function quote(Quote $quote, ?Invoice $invoice = null)
    {
        return $this->load($this->html($quote, $invoice));
    }

    public function receipt(Payment $payment)
    {
        $payment->loadMissing('quote', 'customer', 'recorder');
        $company = Company::current();

        return $this->load(view('pdf.receipt', [
            'payment' => $payment,
            'quote' => $payment->quote,
            'company' => $company,
            'logo' => $company->logo_path ? $this->dataUri($company->logo_path, 360) : null,
            'fontRegular' => $this->fontPath('NotoSans-Regular.ttf'),
            'fontBold' => $this->fontPath('NotoSans-Bold.ttf'),
        ])->render());
    }

    /** The HTML handed to dompdf. */
    public function html(Quote $quote, ?Invoice $invoice = null): string
    {
        $quote->loadMissing('lines', 'creator');
        $company = Company::current();

        return view('pdf.quote', [
            'quote' => $quote,
            'invoice' => $invoice,
            'company' => $company,
            'logo' => $company->logo_path ? $this->dataUri($company->logo_path, 360) : null,
            'photos' => $quote->lines->mapWithKeys(fn ($l) => [
                $l->id => ! empty($l->photos) ? $this->dataUri($l->photos[0], 240) : null,
            ]),
            'fontRegular' => $this->fontPath('NotoSans-Regular.ttf'),
            'fontBold' => $this->fontPath('NotoSans-Bold.ttf'),
        ])->render();
    }

    public static function filename(Quote $quote, ?Invoice $invoice = null): string
    {
        return str_replace('/', '-', $invoice ? $invoice->number : $quote->display_number).'.pdf';
    }

    private function load(string $html)
    {
        File::ensureDirectoryExists(storage_path('fonts'));

        return Pdf::loadHTML($html)->setPaper('a4')->setOption([
            'chroot' => base_path(),
            'font_dir' => storage_path('fonts'),
            'font_cache' => storage_path('fonts'),
            'default_font' => 'Noto Sans',
            'is_remote_enabled' => false,
            'is_font_subsetting_enabled' => true,
            'dpi' => 96,
        ]);
    }

    private function fontPath(string $file): string
    {
        return str_replace('\\', '/', resource_path('fonts/'.$file));
    }

    /** Downscale with GD and inline as a JPEG/PNG data URI. */
    private function dataUri(string $path, int $maxWidth): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }
        $img = @imagecreatefromstring($disk->get($path));
        if (! $img) {
            return null;
        }
        if (imagesx($img) > $maxWidth) {
            $img = imagescale($img, $maxWidth);
        }

        $isPng = str_ends_with(strtolower($path), '.png');
        ob_start();
        if ($isPng) {
            imagesavealpha($img, true);
            imagepng($img);
        } else {
            imagejpeg($img, null, 80);
        }
        $data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/'.($isPng ? 'png' : 'jpeg').';base64,'.base64_encode($data);
    }
}
