<?php

/**
 * -------------------------------------------------------------------------
 * Zimmet plugin — PDF motoru (Kurumsal tasarım)
 * -------------------------------------------------------------------------
 *
 * Kurumsal "Zimmet / Teslim-Tesellüm Tutanağı" tasarımını üretir.
 * Türkçe karakterler için DejaVu Sans (Unicode). Islak imza için boş
 * imza alanları. Her belge DAİMA tek A4 sayfaya sığar: alt bloklar sabit
 * koordinatlara sabitlenir, taahhüt metni gerekirse otomatik küçültülür.
 *
 * Artsution tarafından geliştirilmiştir — https://github.com/erdogankamar/zimmet
 * @copyright Copyright (c) 2026 Artsution
 * @license   GPLv3+
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginZimmetPdf extends TCPDF
{
    private const FIRST_PAGE_ROWS = 10;
    private const CONTINUATION_PAGE_ROWS = 20;

    /** Tüm çerçeveli kutular için ortak köşe radüsü (mm) */
    private const BOX_RADIUS = 2.5;

    /** Koyu lacivert (#1a428a) */
    private $navy = [26, 66, 138];
    /** Orta mavi (başlık çubukları) (#2e6fb0) */
    private $blue = [46, 111, 176];
    /** Açık mavi vurgu (#4d9ed7) */
    private $accent = [77, 158, 215];
    /** Tablo başlığı açık zemin */
    private $thBg = [221, 228, 238];
    /** Zebra satır rengi */
    private $zebra = [246, 249, 252];
    /** Kenarlık grisi */
    private $border = [205, 213, 224];
    /** Etiket grisi */
    private $gray = [110, 120, 135];

    private $footerInfo = '';
    private $baseFont = 'dejavusans';

    public function __construct($font = 'dejavusans')
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->baseFont = $font ?: 'dejavusans';

        $this->SetCreator('GLPI - Zimmet (Artsution)');
        $this->SetAuthor('Artsution');
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->SetMargins(12, 10, 12);
        $this->SetAutoPageBreak(false, 8); // tek sayfa garantisi
        $this->SetFont($this->baseFont, '', 9);
    }

    /**
     * Sayfa altı bilgi şeridi.
     */
    public function Footer()
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.2);
        $this->Line(12, $this->GetY(), $this->getPageWidth() - 12, $this->GetY());
        $this->SetFont($this->baseFont, '', 7);
        $this->SetTextColor(...$this->gray);
        $footerY = $this->GetY();
        $footerW = $this->getPageWidth() - 24;
        $pageText = $this->getPageNumGroupAlias() . ' / ' . $this->getPageGroupAlias();

        $this->SetXY(12, $footerY);
        $this->Cell($footerW * 0.70, 8, $this->footerInfo, 0, 0, 'L');
        $this->SetXY(30, $footerY);
        $this->Cell($footerW, 8, $pageText, 0, 0, 'R');
    }

    /**
     * Tek bir tutanağı 10 satırlık kurumsal sayfa blokları olarak ekler.
     */
    public function addDocumentPage(array $data)
    {
        $docNo   = $this->dec($data['document_no'] ?? '');
        $rev     = $this->dec($data['revision'] ?? '');
        $footerDate = $data['revision_date'] ?? ($data['date_str'] ?? '');
        $this->footerInfo = trim(
            ($docNo ? 'Doküman No: ' . $docNo : '')
            . ($rev ? '   |   Rev: ' . $rev : '')
            . ($footerDate ? '   |   ' . $footerDate : '')
        );

        $allLines = array_values($data['lines'] ?? []);
        $firstLines = array_slice($allLines, 0, self::FIRST_PAGE_ROWS);
        $continuationLines = array_slice($allLines, self::FIRST_PAGE_ROWS);
        $chunks = array_chunk($continuationLines, self::CONTINUATION_PAGE_ROWS);
        array_unshift($chunks, $firstLines);
        if (empty($chunks)) {
            $chunks = [[]];
        }

        $this->startPageGroup();
        $this->AddPage();

        $W  = $this->getPageWidth() - 24; // içerik genişliği
        $x0 = 12;

        $y = $this->drawDocInfo($data, $x0, 8, $W);
        $y = $this->drawHeader($data, $x0, $y + 1, $W);
        $y = $this->drawIdentity($data, $x0, $y + 2, $W);

        // --- Alt bloklar sabit koordinatlara sabitlenir (tek sayfa) ---
        $pageBottom = $this->getPageHeight() - 14;
        $signH = 30;
        $signTop = $pageBottom - $signH;
        $tableBottom = $signTop - 4;

        // Tablo yüksekliği: başlık çubuğu + sütun başlığı + satırlar
        $barH = 7;
        $colH = 6;
        $rowsCount = self::FIRST_PAGE_ROWS;
        $rowH = 6.8;
        $tableH = $barH + $colH + $rowsCount * $rowH;
        $tableTop = $tableBottom - $tableH;

        // Tarih / İmza bloğu tablonun üstünde
        $tarihH = 13;
        $tarihTop = $tableTop - 3 - $tarihH;

        // Taahhüt bloğu: kimlik altından tarih bloğuna kadar
        $commitTop = $y + 2;
        $commitBottom = $tarihTop - 3;

        $pageData = $data;
        $pageData['lines'] = $chunks[0];

        $this->drawCommitment($data, $x0, $commitTop, $W, $commitBottom - $commitTop);
        $this->drawTarihImza($data, $x0, $tarihTop, $W, $tarihH);
        $this->drawEquipmentTable($pageData, $x0, $tableTop, $W, $barH, $colH, $rowH, $rowsCount, 1);
        $this->drawSignatures($data, $x0, $signTop, $W, $signH);

        $pageCount = count($chunks);
        for ($i = 1; $i < $pageCount; $i++) {
            $pageData = $data;
            $pageData['lines'] = $chunks[$i];
            $this->addContinuationPage(
                $pageData,
                $i + 1,
                $pageCount,
                self::FIRST_PAGE_ROWS + (($i - 1) * self::CONTINUATION_PAGE_ROWS) + 1
            );
        }
    }

    // ====================================================================
    //  BÖLÜMLER
    // ====================================================================

    /**
     * Üst başlık: logo + belge başlığı.
     *
     * @return float  Bloğun alt Y koordinatı
     */
    private function drawHeader(array $data, $x, $y, $W)
    {
        $h = 30;
        $title = ($data['doc_type'] ?? 'zimmet') === 'tesellum'
            ? 'TESLİM-TESELLÜM TUTANAĞI'
            : 'ZİMMET TESLİM TUTANAĞI';
        $subtitle = $this->dec($data['header_subtitle'] ?? '');
        $company  = $this->dec($data['header_title'] ?? '');

        // Çerçeve
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($x, $y, $W, $h, self::BOX_RADIUS, '1111', 'DF');

        // Sol mavi vurgu şeridi (kutu radüsüne uyumlu)
        $this->SetFillColor(...$this->navy);
        $this->RoundedRect($x, $y, 3, $h, self::BOX_RADIUS, '1001', 'F');

        // Logo (varsa) sol blok
        $logoRight = $x + 10;
        if (!empty($data['logo_path']) && @is_file($data['logo_path'])) {
            try {
                $this->Image(
                    $data['logo_path'],
                    $x + 8,
                    $y + 5,
                    52,
                    $h - 10,
                    '',
                    '',
                    '',
                    false,
                    300,
                    '',
                    false,
                    false,
                    0,
                    'LM'
                );
                $logoRight = $x + 64;
            } catch (\Throwable $e) {
                $logoRight = $x + 10;
            }
        } else {
            // Logo yoksa kurum adını sola yaz
            $this->SetTextColor(...$this->navy);
            $this->SetFont($this->baseFont, 'B', 13);
            $this->SetXY($x + 8, $y + 8);
            $this->MultiCell(54, 14, $company, 0, 'L', false, 0, '', '', true, 0, false, true, 14, 'M');
            $logoRight = $x + 64;
        }

        // Dikey ayraç
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->Line($logoRight, $y + 5, $logoRight, $y + $h - 5);

        // Sağ blok metinleri
        $tx = $logoRight + 6;
        $tw = $x + $W - $tx - 4;

        $this->SetTextColor(...$this->navy);
        $this->SetFont($this->baseFont, 'B', 17);
        $this->SetXY($tx, $y + 5.5);
        $this->Cell($tw, 9, $title, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        $this->SetTextColor(...$this->blue);
        $this->SetFont($this->baseFont, 'B', 9.5);
        $this->SetXY($tx, $y + 15.5);
        $this->Cell($tw, 6, $subtitle, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        $this->SetTextColor(...$this->gray);
        $this->SetFont($this->baseFont, '', 7.5);
        $this->SetXY($tx, $y + 21);
        $this->Cell($tw, 5, 'ENVANTER YÖNETİM FORMU', 0, 0, 'L', false, '', 0, false, 'T', 'M');

        return $y + $h;
    }

    /**
     * 10 satırı aşan tutanaklarda ekipman listesi devam sayfası.
     */
    private function addContinuationPage(array $data, $pageNo, $pageCount, $startNo)
    {
        $this->AddPage();

        $W  = $this->getPageWidth() - 24;
        $x0 = 12;

        $y = $this->drawDocInfo($data, $x0, 8, $W);
        $y = $this->drawHeader($data, $x0, $y + 1, $W);
        $y = $this->drawIdentity($data, $x0, $y + 2, $W);
        $y = $this->drawContinuationNotice($x0, $y + 3, $W, $pageNo, $pageCount);

        $pageBottom = $this->getPageHeight() - 14;
        $signH = 25;
        $signTop = $pageBottom - $signH;
        $tableBottom = $signTop - 4;

        $barH = 7;
        $colH = 6;
        $rowsCount = self::CONTINUATION_PAGE_ROWS;
        $availableH = $tableBottom - ($y + 5);
        $rowH = ($availableH - $barH - $colH) / $rowsCount;
        $rowH = min(8.2, max(6.2, $rowH));
        $tableTop = $y + 5;

        $this->drawEquipmentTable(
            $data,
            $x0,
            $tableTop,
            $W,
            $barH,
            $colH,
            $rowH,
            $rowsCount,
            $startNo,
            'TESLİM EDİLEN EKİPMANLAR - DEVAM'
        );
        $this->drawSignatureInitials($data, $x0, $signTop, $W, $signH);
    }

    /**
     * Fiş no / tutanak tarihi bilgi satırı.
     *
     * @return float
     */
    private function drawDocInfo(array $data, $x, $y, $W)
    {
        $h = 6.5;
        $receiptNo = $this->dec($data['receipt_no'] ?? '');
        $date  = $data['date_str'] ?? '';
        $gap = 1.2;

        $this->SetFont($this->baseFont, 'B', 9);
        $this->SetXY($x + 4, $y);
        $this->SetTextColor(20, 20, 20);
        $receiptLabel = 'Fiş No:';
        $receiptLabelW = $this->GetStringWidth($receiptLabel);
        $this->Cell($receiptLabelW, $h, $receiptLabel, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $this->SetX($this->GetX() + $gap);
        $this->SetTextColor(...$this->navy);
        $this->Cell(75, $h, $receiptNo, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        $dateLabel = 'Tarih:';
        $this->SetFont($this->baseFont, 'B', 9);
        $dateLabelW = $this->GetStringWidth($dateLabel);
        $dateValueW = $this->GetStringWidth($date);
        $dateX = $x + $W - $dateLabelW - $gap - $dateValueW - 4;
        $this->SetXY($dateX, $y);
        $this->SetTextColor(20, 20, 20);
        $this->Cell($dateLabelW, $h, $dateLabel, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $this->SetX($this->GetX() + $gap);
        $this->SetTextColor(...$this->navy);
        $this->Cell($dateValueW, $h, $date, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        return $y + $h;
    }

    private function drawContinuationNotice($x, $y, $W, $pageNo, $pageCount)
    {
        $h = 11;
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.25);
        $this->SetFillColor(...$this->zebra);
        $this->RoundedRect($x, $y, $W, $h, self::BOX_RADIUS, '1111', 'DF');

        $this->SetTextColor(...$this->navy);
        $this->SetFont($this->baseFont, 'B', 8.5);
        $this->SetXY($x + 5, $y + 2.5);
        $this->Cell(
            $W - 10,
            5,
            sprintf('EKİPMAN LİSTESİ DEVAMI  |  Sayfa %d / %d', $pageNo, $pageCount),
            0,
            0,
            'L',
            false,
            '',
            0,
            false,
            'T',
            'M'
        );

        return $y + $h;
    }

    /**
     * ADI SOYADI / DEPARTMAN-GÖREV kutuları.
     *
     * @return float
     */
    private function drawIdentity(array $data, $x, $y, $W)
    {
        $h = 16;
        $gap = 4;
        $bw = ($W - $gap) / 2;

        $this->idBox($x, $y, $bw, $h, 'ADI SOYADI', $this->dec($data['fullname'] ?? ''));
        $this->idBox(
            $x + $bw + $gap,
            $y,
            $bw,
            $h,
            'DEPARTMAN / GÖREV',
            $this->dec($data['department'] ?? ''),
            $this->dec($data['job_title'] ?? '')
        );

        return $y + $h;
    }

    private function idBox($x, $y, $w, $h, $label, $value, $value2 = null)
    {
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->SetFillColor(...$this->zebra);
        $this->RoundedRect($x, $y, $w, $h, self::BOX_RADIUS, '1111', 'DF');

        // Sol vurgu şeridi (kutu radüsüne uyumlu)
        $this->SetFillColor(...$this->navy);
        $this->RoundedRect($x, $y, 2.5, $h, self::BOX_RADIUS, '1001', 'F');

        // Yatay iç boşluklar: sol şeritten sonra 6mm, sağda 3mm
        $padL = 6;
        $valW = $w - $padL - 3;

        // Etiket
        $this->SetTextColor(...$this->gray);
        $this->SetFont($this->baseFont, 'B', 7);
        $this->SetXY($x + $padL, $y + 2.6);
        $this->Cell($valW, 3.6, $label, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        if ($value2 !== null && $value2 !== '') {
            // İki satır: Departman üstte, Görev altta — dikey ritim ~4.6mm
            $this->idValueLine($x + $padL, $y + 6.2, $valW, $value, 9.5, 4.6);
            $this->idValueLine($x + $padL, $y + 10.8, $valW, $value2, 9.5, 4.6);
        } else {
            // Tek satır — etiket altında kalan alanda dikey ortalı
            $this->idValueLine($x + $padL, $y + 6.5, $valW, $value, 12.5, 7.5);
        }
    }

    /**
     * Bir değer satırını kutuya sığacak şekilde (gerekirse fontu küçülterek) basar.
     */
    private function idValueLine($x, $y, $w, $value, $startFont, $cellH)
    {
        $this->SetTextColor(...$this->navy);
        $minFont = 6.5;
        $font    = $startFont;
        $this->SetFont($this->baseFont, 'B', $font);
        while ($font > $minFont && $this->GetStringWidth($value) > $w) {
            $font -= 0.5;
            $this->SetFont($this->baseFont, 'B', $font);
        }
        $this->SetXY($x, $y);
        $this->Cell($w, $cellH, $value, 0, 0, 'L', false, '', 0, false, 'T', 'M');
    }

    /**
     * Taahhütname bölümü: mavi başlık çubuğu + metin kutusu.
     * Metin, kutuya sığacak şekilde gerekirse otomatik küçültülür.
     */
    private function drawCommitment(array $data, $x, $y, $W, $maxH)
    {
        $barH = 7;
        $gapH = 2;
        $this->sectionBar($x, $y, $W, $barH, 'ZİMMET VE KULLANIM TAAHHÜTNAMESİ', 'L');

        $boxY = $y + $barH + $gapH;
        $boxH = max(20, $maxH - $barH - $gapH);

        // Çerçeve
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($x, $boxY, $W, $boxH, self::BOX_RADIUS, '1111', 'DF');

        $html    = $data['commitment_text'] ?? '';
        $innerW  = $W - 8;
        $startY  = $boxY + 3;
        $availH  = $boxH - 5;      // üst/alt iç boşluk
        $minFont = 5.5;

        // Satır aralığını biraz sıkılaştır (daha çok metin sığar)
        $this->setCellHeightRatio(1.15);

        // Kesin ölçüm: metni dene, kutuya sığmazsa geri al, fontu düşür
        $font = 8.5;
        $placed = false;
        while ($font >= $minFont) {
            $this->startTransaction();
            $this->SetFont($this->baseFont, '', $font);
            $this->SetTextColor(40, 40, 40);
            $this->writeHTMLCell(
                $innerW,
                0,
                $x + 4,
                $startY,
                $html,
                0,
                1,
                false,
                true,
                'J',
                true
            );
            $used = $this->GetY() - $startY;

            if ($used <= $availH) {
                $this->commitTransaction(); // bu render'ı koru
                $placed = true;
                break;
            }
            $this->rollbackTransaction(true); // geri al, daha küçük dene
            $font -= 0.5;
        }

        // En küçük fontta bile taşıyorsa son halini yine de bas
        if (!$placed) {
            $this->SetFont($this->baseFont, '', $minFont);
            $this->SetTextColor(40, 40, 40);
            $this->writeHTMLCell($innerW, 0, $x + 4, $startY, $html, 0, 1, false, true, 'J', true);
        }

        $this->setCellHeightRatio(1.25); // varsayılana dön
    }

    /**
     * TARİH / İMZA satırı.
     */
    private function drawTarihImza(array $data, $x, $y, $W, $h)
    {
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($x, $y, $W, $h, self::BOX_RADIUS, '1111', 'DF');

        $half = $W / 2;
        // Dikey ayraç
        $this->Line($x + $half, $y + 2, $x + $half, $y + $h - 2);

        // TARİH
        $this->SetTextColor(...$this->gray);
        $this->SetFont($this->baseFont, 'B', 7);
        $this->SetXY($x + 5, $y + 2);
        $this->Cell(20, 4, 'TARİH', 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $this->SetTextColor(...$this->navy);
        $this->SetFont($this->baseFont, 'B', 11);
        $this->SetXY($x + 5, $y + 6);
        $this->Cell($half - 10, 6, $data['date_str'] ?? '', 0, 0, 'L', false, '', 0, false, 'T', 'M');

        // İMZA
        $this->SetTextColor(...$this->gray);
        $this->SetFont($this->baseFont, 'B', 7);
        $this->SetXY($x + $half + 5, $y + 2);
        $this->Cell(20, 4, 'İMZA', 0, 0, 'L', false, '', 0, false, 'T', 'M');
    }

    /**
     * Ekipman tablosu (zebra, durum mavi).
     */
    private function drawEquipmentTable(
        array $data,
        $x,
        $y,
        $W,
        $barH,
        $colH,
        $rowH,
        $rowsCount,
        $startNo = 1,
        $title = 'TESLİM EDİLEN EKİPMANLAR'
    )
    {
        $lines = $data['lines'] ?? [];

        // Başlık çubuğu
        $this->sectionBar($x, $y, $W, $barH, $title, 'C');
        $cy = $y + $barH;

        // Sütun genişlikleri
        $cols = [
            'sira'  => $W * 0.07,
            'ad'    => $W * 0.43,
            'idno'  => $W * 0.20,
            'durum' => $W * 0.14,
            'mik'   => $W * 0.08,
            'cins'  => $W * 0.08,
        ];

        // Sütun başlık satırı — bölücü çizgiler BEYAZ (zebra üstünde ayraç)
        $this->SetFillColor(...$this->thBg);
        $this->SetTextColor(...$this->navy);
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(0.6);
        $this->SetFont($this->baseFont, 'B', 7.5);
        $this->SetXY($x, $cy);
        $this->Cell($cols['sira'], $colH, 'SIRA', 1, 0, 'C', true);
        $this->Cell($cols['ad'], $colH, ' EKİPMAN ADI', 1, 0, 'L', true);
        $this->Cell($cols['idno'], $colH, 'SERİ / STOK NO', 1, 0, 'C', true);
        $this->Cell($cols['durum'], $colH, 'DURUM', 1, 0, 'C', true);
        $this->Cell($cols['mik'], $colH, 'MİKTAR', 1, 0, 'C', true);
        $this->Cell($cols['cins'], $colH, 'CİNSİ', 1, 1, 'C', true);
        $cy += $colH;

        // Veri satırları
        for ($i = 0; $i < $rowsCount; $i++) {
            $line = $lines[$i] ?? null;

            $seri = $this->dec($line['serial'] ?? '');
            $stok = $this->dec($line['otherserial'] ?? '');
            $idno = $seri !== '' ? $seri : $stok;

            $ad    = $this->dec($line['item_name'] ?? '');
            $durum = $this->dec($line['state_name'] ?? '');
            $mik   = $line ? $this->fmtQty($line['quantity'] ?? 1) : '';
            $cins  = $this->dec($line['unit'] ?? '');

            // Zebra
            $fill = ($i % 2 === 1);
            if ($fill) {
                $this->SetFillColor(...$this->zebra);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $this->SetXY($x, $cy);
            $this->SetTextColor(60, 60, 60);
            $this->SetFont($this->baseFont, '', 7.8);

            $this->Cell($cols['sira'], $rowH, (string) ($startNo + $i), 'LRB', 0, 'C', true);

            // Ekipman adı (gerekirse küçült)
            $adFont = 7.8;
            while ($adFont > 6 && $this->GetStringWidth($ad) > ($cols['ad'] - 3)) {
                $adFont -= 0.3;
                $this->SetFont($this->baseFont, '', $adFont);
            }
            $this->Cell($cols['ad'], $rowH, ' ' . $ad, 'LRB', 0, 'L', true, '', 1);
            $this->SetFont($this->baseFont, '', 7.8);

            $this->fitCell($cols['idno'], $rowH, $idno, 'LRB', 'C', true, 6.8, 5.6);

            // Durum (mavi, bold)
            $this->SetTextColor(...$this->blue);
            $this->SetFont($this->baseFont, 'B', 7.5);
            $this->Cell($cols['durum'], $rowH, $durum, 'LRB', 0, 'C', true);

            $this->SetTextColor(60, 60, 60);
            $this->SetFont($this->baseFont, '', 7.8);
            $this->Cell($cols['mik'], $rowH, $mik, 'LRB', 0, 'C', true);
            $this->Cell($cols['cins'], $rowH, $cins, 'LRB', 1, 'C', true);

            $cy += $rowH;
        }
    }

    /**
     * TESLİM EDEN / TESLİM ALAN imza kutuları (boş — ıslak imza).
     */
    private function drawSignatures(array $data, $x, $y, $W, $h)
    {
        $gap = 6;
        $bw = ($W - $gap) / 2;

        $this->signBox(
            $x,
            $y,
            $bw,
            $h,
            'TESLİM EDEN',
            $this->dec($data['tech_name'] ?? ''),
            $this->dec($data['tech_title'] ?? '')
        );
        $this->signBox(
            $x + $bw + $gap,
            $y,
            $bw,
            $h,
            'TESLİM ALAN',
            $this->dec($data['fullname'] ?? ''),
            $this->dec($data['job_title'] ?? '')
        );
    }

    private function signBox($x, $y, $w, $h, $title, $name, $jobTitle = '')
    {
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($x, $y, $w, $h, self::BOX_RADIUS, '1111', 'DF');

        $pad = 7;
        $innerW = $w - 2 * $pad;

        // --- ÜST GRUP: başlık + ayırıcı çizgi + etiket (kutunun üstüne toplanır) ---
        $this->SetTextColor(...$this->navy);
        $this->SetFont($this->baseFont, 'B', 10);
        $this->SetXY($x + $pad, $y + 3.8);
        $this->Cell($innerW, 5, $title, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        $this->SetDrawColor(200, 206, 214);
        $this->SetLineWidth(0.3);
        $this->Line($x + $pad, $y + 9.6, $x + $w - $pad, $y + 9.6);

        $this->SetTextColor(...$this->gray);
        $this->SetFont($this->baseFont, '', 6.8);
        $this->SetXY($x + $pad, $y + 10.4);
        $this->Cell($innerW, 4, 'AD-SOYAD / ÜNVAN / İMZA', 0, 0, 'L', false, '', 0, false, 'T', 'M');

        // --- ALT GRUP: isim (+ünvan) alt bölgede dikey ortalı (üstte ıslak imza payı) ---
        $zoneMid = $y + ($h + 15) / 2; // etiket altından kutu tabanına kadarki alanın ortası

        if ($jobTitle !== '') {
            $this->SetTextColor(...$this->navy);
            $this->fitTextLine($x + $pad, $zoneMid - 4.6, $innerW, $name, 'B', 9.8, 7, 4.4);
            $this->SetTextColor(...$this->gray);
            $this->fitTextLine($x + $pad, $zoneMid - 0.1, $innerW, $jobTitle, '', 8, 6, 4.4);
        } else {
            $this->SetTextColor(...$this->navy);
            $this->fitTextLine($x + $pad, $zoneMid - 2.6, $innerW, $name, 'B', 10.2, 7, 5.2);
        }
    }

    /**
     * Tek satır metni, verilen genişliğe gerekirse fontu küçülterek hizalar.
     */
    private function fitTextLine($x, $y, $w, $text, $style, $startFont, $minFont, $cellH)
    {
        $font = $startFont;
        $this->SetFont($this->baseFont, $style, $font);
        while ($font > $minFont && $this->GetStringWidth($text) > $w) {
            $font -= 0.3;
            $this->SetFont($this->baseFont, $style, $font);
        }
        $this->SetXY($x, $y);
        $this->Cell($w, $cellH, $text, 0, 0, 'L', false, '', 0, false, 'T', 'M');
    }

    private function drawSignatureInitials(array $data, $x, $y, $W, $h)
    {
        $gap = 6;
        $bw = ($W - $gap) / 2;

        $this->initialBox(
            $x,
            $y,
            $bw,
            $h,
            'TESLİM EDEN PARAF',
            $this->dec($data['tech_name'] ?? ''),
            $this->dec($data['tech_title'] ?? '')
        );
        $this->initialBox(
            $x + $bw + $gap,
            $y,
            $bw,
            $h,
            'TESLİM ALAN PARAF',
            $this->dec($data['fullname'] ?? ''),
            $this->dec($data['job_title'] ?? '')
        );
    }

    private function initialBox($x, $y, $w, $h, $title, $name, $jobTitle = '')
    {
        $this->SetDrawColor(...$this->border);
        $this->SetLineWidth(0.3);
        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($x, $y, $w, $h, self::BOX_RADIUS, '1111', 'DF');

        $pad = 7;
        $innerW = $w - 2 * $pad;

        // --- ÜST GRUP: başlık + ayırıcı + etiket (kutunun üstüne toplanır) ---
        $this->SetTextColor(...$this->navy);
        $this->SetFont($this->baseFont, 'B', 9.5);
        $this->SetXY($x + $pad, $y + 3.2);
        $this->Cell($innerW, 5, $title, 0, 0, 'L', false, '', 0, false, 'T', 'M');

        $this->SetDrawColor(200, 206, 214);
        $this->SetLineWidth(0.3);
        $this->Line($x + $pad, $y + 8.4, $x + $w - $pad, $y + 8.4);

        $this->SetTextColor(...$this->gray);
        $this->SetFont($this->baseFont, '', 6.3);
        $this->SetXY($x + $pad, $y + 8.9);
        $this->Cell($innerW, 3.4, 'AD-SOYAD / ÜNVAN / PARAF', 0, 0, 'L', false, '', 0, false, 'T', 'M');

        // --- ALT GRUP: isim (+ünvan) alt bölgede dikey ortalı ---
        $zoneMid = $y + ($h + 12) / 2;

        if ($jobTitle !== '') {
            $this->SetTextColor(...$this->navy);
            $this->fitTextLine($x + $pad, $zoneMid - 4.2, $innerW, $name, 'B', 9, 6.5, 4);
            $this->SetTextColor(...$this->gray);
            $this->fitTextLine($x + $pad, $zoneMid - 0.1, $innerW, $jobTitle, '', 7.3, 5.5, 4);
        } else {
            $this->SetTextColor(...$this->navy);
            $this->fitTextLine($x + $pad, $zoneMid - 2.4, $innerW, $name, 'B', 9.5, 6.5, 4.6);
        }
    }

    // ====================================================================
    //  YARDIMCILAR
    // ====================================================================

    /**
     * Uzun seri/stok gibi değerleri hücre sınırından taşırmadan basar.
     */
    private function fitCell($w, $h, $text, $border = 0, $align = 'L', $fill = false, $maxFont = 7.8, $minFont = 5.6)
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $text = $this->dec($text);
        $innerW = max(1, $w - 2);
        $font = $maxFont;

        while ($font > $minFont && $this->GetStringWidth($text) > $innerW) {
            $font -= 0.3;
            $this->SetFont($this->baseFont, '', $font);
        }

        $this->MultiCell(
            $w,
            $h,
            $text,
            $border,
            $align,
            $fill,
            0,
            $x,
            $y,
            true,
            0,
            false,
            true,
            $h,
            'M',
            true
        );
        $this->SetXY($x + $w, $y);
    }

    /**
     * Mavi başlık çubuğu (beyaz metin).
     */
    private function sectionBar($x, $y, $w, $h, $text, $align = 'L')
    {
        $this->SetFillColor(...$this->navy);
        $this->RoundedRect($x, $y, $w, $h, 1.8, '1111', 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont($this->baseFont, 'B', 9.5);
        $this->SetXY($x + ($align === 'C' ? 0 : 4), $y);
        $this->Cell($align === 'C' ? $w : $w - 8, $h, $text, 0, 0, $align, false, '', 0, false, 'T', 'M');
    }

    /**
     * GLPI sanitize'ını geri al + HTML entity çöz + yinelenen önek temizle.
     */
    private function dec($s)
    {
        $s = (string) $s;
        if (class_exists('\\Glpi\\Toolbox\\Sanitizer')) {
            $s = \Glpi\Toolbox\Sanitizer::unsanitize($s);
        }
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/^(.+?\s[—-]\s)\1+/u', '$1', $s);
        return $s;
    }

    private function fmtQty($qty)
    {
        $qty = (float) $qty;
        return ($qty == (int) $qty)
            ? (string) (int) $qty
            : rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
    }

    /**
     * PDF içeriğini string olarak döndürür.
     */
    public function getContent()
    {
        return $this->Output('', 'S');
    }
}
