<?php

namespace App\Support;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

final class LabPortalQrDataUri
{
    /**
     * SVG QR as a data URI (no external HTTP) for narrow thermal receipts.
     */
    public static function fromUrl(string $url): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 120,
            margin: 4,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $writer = new SvgWriter;
        $result = $writer->write($qrCode);

        return 'data:image/svg+xml;base64,'.base64_encode($result->getString());
    }
}
