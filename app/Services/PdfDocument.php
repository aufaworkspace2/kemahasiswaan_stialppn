<?php

namespace App\Services;

class PdfDocument
{
    private function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: '';

        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $encoded);
    }

    /** @return array<int, array{runs: array<int, array{font: string, text: string}>}> */
    private function richRows(string $html): array
    {
        $document = new \DOMDocument;
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $rows = [];
        $runs = [];
        $length = 0;
        $flush = function (bool $blank = false) use (&$rows, &$runs, &$length): void {
            if ($runs || $blank) {
                $rows[] = ['runs' => $runs];
            }
            $runs = [];
            $length = 0;
        };
        $append = function (string $text, string $font) use (&$runs, &$length, $flush): void {
            foreach (preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
                $space = preg_match('/^\s+$/u', $token) === 1;
                if ($space) {
                    $token = ' ';
                    if ($length === 0) {
                        continue;
                    }
                }
                foreach (mb_str_split($token, 82) as $part) {
                    $size = mb_strlen($part);
                    if ($length + $size > 82 && $length > 0) {
                        $flush();
                        if ($space) {
                            continue;
                        }
                    }
                    $last = array_key_last($runs);
                    if ($last !== null && $runs[$last]['font'] === $font) {
                        $runs[$last]['text'] .= $part;
                    } else {
                        $runs[] = ['font' => $font, 'text' => $part];
                    }
                    $length += $size;
                }
            }
        };
        $walk = function (\DOMNode $node, bool $bold = false, bool $italic = false) use (&$walk, &$runs, &$rows, $flush, $append): void {
            if ($node instanceof \DOMText) {
                $append($node->textContent, $bold ? ($italic ? 'F4' : 'F2') : ($italic ? 'F3' : 'F1'));

                return;
            }
            $tag = strtolower($node->nodeName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object'], true)) {
                return;
            }
            if ($tag === 'br') {
                $flush(true);

                return;
            }
            $block = in_array($tag, ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote'], true);
            if ($block && $runs) {
                $flush();
            }
            if ($tag === 'li') {
                $number = 1;
                for ($sibling = $node->previousSibling; $sibling; $sibling = $sibling->previousSibling) {
                    if ($sibling->nodeName === 'li') {
                        $number++;
                    }
                }
                $append($node->parentNode?->nodeName === 'ol' ? $number.'. ' : '- ', 'F1');
            }
            foreach ($node->childNodes as $child) {
                $walk($child, $bold || in_array($tag, ['strong', 'b', 'h2', 'h3'], true), $italic || in_array($tag, ['em', 'i'], true));
            }
            if ($block && $runs) {
                $flush();
            }
            if (in_array($tag, ['p', 'h2', 'h3', 'blockquote'], true) && $rows && end($rows)['runs']) {
                $flush(true);
            }
        };
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $node) {
                $walk($node);
            }
        }
        $flush();
        while ($rows && ! end($rows)['runs']) {
            array_pop($rows);
        }

        return $rows;
    }

    /** @param array<int, string|array{html: string}> $lines */
    public function render(array $lines): string
    {
        $wrapped = [];
        foreach (array_slice($lines, 2) as $line) {
            if (is_array($line)) {
                array_push($wrapped, ...$this->richRows($line['html']));

                continue;
            }
            foreach (explode("\n", wordwrap($line, 82, "\n", true)) as $part) {
                $wrapped[] = ['runs' => [['font' => 'F1', 'text' => $part]]];
            }
        }
        $chunks = array_chunk($wrapped, 35);
        if (! $chunks) {
            $chunks = [[]];
        }
        $logo = imagecreatefromwebp(public_path('assets/logo.webp'));
        $image = imagecreatetruecolor(imagesx($logo), imagesy($logo));
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        imagecopy($image, $logo, 0, 0, 0, 0, imagesx($logo), imagesy($logo));
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = ob_get_clean();
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>', 5 => '<< /Type /XObject /Subtype /Image /Width '.imagesx($image).' /Height '.imagesy($image).' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg).">>\nstream\n".$jpeg."\nendstream"];
        $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
        $objects[7] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding >>';
        $kids = [];
        foreach ($chunks as $index => $chunk) {
            $pageId = 8 + $index * 2;
            $contentId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $stream = "q 45 0 0 50 53 745 cm /Logo Do Q\n0.12 0.16 0.13 rg\nBT /F2 20 Tf 115 775 Td (STIA LPPN) Tj ET\nBT /F2 11 Tf 115 753 Td (BAGIAN KEMAHASISWAAN) Tj ET\n0.1 0.15 0.1 RG 1.2 w 50 730 m 545 730 l S\nBT /F1 11 Tf 50 699 Td 18 TL\n";
            foreach ($chunk as $part) {
                foreach ($part['runs'] as $run) {
                    $stream .= '/'.$run['font'].' 11 Tf ('.$this->escape($run['text']).") Tj\n";
                }
                $stream .= "T*\n";
            }
            $stream .= "ET\nBT /F1 8 Tf 50 40 Td (STIA LPPN - Kemahasiswaan) Tj ET\nBT /F1 8 Tf 490 40 Td (".($index + 1).' / '.count($chunks).') Tj ET';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 6 0 R /F4 7 0 R >> /XObject << /Logo 5 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($chunks).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 ".$size."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
