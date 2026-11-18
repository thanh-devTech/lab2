<?php
class CI_Pix_Chart
{
    protected array $data = [];
    protected array $colors = [];
    protected int $width = 800;
    protected int $height = 800;

    protected float $paddingRatio = 0.1;   // 10% padding
    protected float $holeRatio = 0.55;     // Circle  Round doughnut

    public function __construct(array $data = [], array $colors = [], $w = 800, $h = 800)
    {
        $this->data = $data;
        $this->colors = $colors;
        $this->width = $w;
        $this->height = $h;
    }

    public function setWith($w): void
    {
        $this->width = $w;
    }

    public function setHeight($h): void
    {
        $this->height = $h;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setColors(array $colors): void
    {
        $this->colors = $colors;
    }

    private function allocateColor($img, string $color)
    {
        $color = trim($color);

        // HEX #RRGGBB hoặc #RGB
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color)) {
            $hex = ltrim($color, '#');

            if (strlen($hex) === 3) {
                $hex = "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}";
            }

            [$r, $g, $b] = sscanf($hex, "%02x%02x%02x");

            return imagecolorallocate($img, $r, $g, $b);
        }

        // rgba(r,g,b,a)
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d\.]+))?\)/i', $color, $m)) {
            $r = (int)$m[1];
            $g = (int)$m[2];
            $b = (int)$m[3];

            // Alpha: CSS 0–1 → GD 0–127
            if (isset($m[4])) {
                $alpha = (float)$m[4];
                $gdAlpha = 127 - (int)round($alpha * 127);
                return imagecolorallocatealpha($img, $r, $g, $b, $gdAlpha);
            }

            return imagecolorallocate($img, $r, $g, $b);
        }

        // Fallback: màu xám
        return imagecolorallocate($img, 150, 150, 150);
    }

    private function create_doughnut_image(array $data, array $colors, $text_total = "Detected", $x_text = 50)
    {
        $width  = $this->width;
        $height = $this->height;

        $cx = $width / 2;
        $cy = $height / 2;

        // === SCALE THEO SIZE ẢNH ===
        $size   = min($width, $height);
        $radius = ($size / 2) * (1 - $this->paddingRatio);
        $holeRadius = $radius * $this->holeRatio;

        $fontSize = max(10, (int)($size * 0.04));
        $font     = max(3, (int)($size / 150));

        // === TẠO CANVAS ===
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        $total = array_sum(array_column($data, 1));

        // === KHÔNG CÓ DỮ LIỆU ===
        if (empty($data) || $total <= 0) {
            $black = imagecolorallocate($img, 0, 0, 0);
            $text = "Data Not Available";

            $textWidth  = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);

            imagestring(
                $img,
                $font,
                ($width - $textWidth) / 2,
                ($height - $textHeight) / 2,
                $text,
                $black
            );

            return $img;
        }

        // === VẼ DOUGHNUT ===
        $start = 270;
        foreach ($data as $i => $row) {
            $angle = ($row[1] / $total) * 360;
            if ($angle < 0.5) continue;
            $angle = (int)round($angle, 0);
            if ($angle < 1) continue;

            $color = $this->allocateColor($img, $colors[$i % count($colors)]);

            imagefilledarc(
                $img,
                $cx,
                $cy,
                $radius * 2,
                $radius * 2,
                $start,
                $start + $angle,
                $color,
                IMG_ARC_PIE
            );

            $start += $angle;
        }

        // === LỖ TRÒN ===
        imagefilledellipse(
            $img,
            $cx,
            $cy,
            $holeRadius * 2,
            $holeRadius * 2,
            $white
        );

        // === TEXT TỔNG (UPGRADED) ===
        $fontFile = TRCPDD_PATH . '/fonts/arial.ttf';
        $black = imagecolorallocate($img, 0, 0, 0);
        $totalText = (string)$total;

        $totalFontSize = max(12, (int)($size * 0.05));

        if (!file_exists($fontFile)) {
            $font = max(3, (int)($size / 150));
            imagestring(
                $img,
                $font,
                $cx - (imagefontwidth($font) * strlen($totalText)) / 2,
                $cy - imagefontheight($font) / 2 - 30,
                $totalText,
                $black
            );
        } else {
            // Tính bounding box
            $bbox = imagettfbbox($totalFontSize, 0, $fontFile, $totalText);
            $textWidth  = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];

            // Center chuẩn theo baseline
            $x = $cx - $textWidth / 2;
            $y = $cy + $textHeight / 2 - 30;

            // Độ đậm tự động
            $bold = max(2, (int)($size / 300));

            // Fake bold
            for ($dx = 0; $dx <= $bold; $dx++) {
                for ($dy = 0; $dy <= $bold; $dy++) {
                    imagettftext(
                        $img,
                        $totalFontSize,
                        0,
                        $x + $dx,
                        $y + $dy,
                        $black,
                        $fontFile,
                        $totalText
                    );
                }
            }
        }

        // === LABEL ===
        if (file_exists(TRCPDD_PATH . '/fonts/arial.ttf')) {
            imagettftext(
                $img,
                $fontSize,
                0,
                $cx - ($fontSize * strlen($text_total)) / 3,
                $cy + $fontSize + 10,
                $black,
                TRCPDD_PATH . '/fonts/arial.ttf',
                $text_total
            );
        }

        return $img;
    }
    public function get_doughnut_base64_by_chart_old($chart, $text_total = "Detected", $x_text = 20)
    {
        $data_chart = [];
        $color_chart = [];
        foreach ($chart->data as $i => $row) {
            if ($i === 0) continue;
            if (empty($row[0]) || !isset($row[1])) continue;
            $data_chart[] = [(string) $row[0], (int) $row[1]];
        }
        $color_chart = $chart->config['colors'];
        return $this->create_doughnut_image_base64($data_chart, $color_chart, $text_total, $x_text);
    }

    public function create_doughnut_image_file($data, $colors, $text_total = "Detected", $x_text = 50)
    {
        $dir = WP_CONTENT_DIR . '/cache/abx';
        $dir_url = WP_CONTENT_URL . '/cache/abx';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $img = $this->create_doughnut_image($data, $colors, $text_total, $x_text);
        $filename_img = 'chart_' . md5(json_encode($data)) . '.png';
        $filepath_img = $dir . '/' . $filename_img;
        imagepng($img, $filepath_img);
        imagedestroy($img);

        return $dir_url . '/' . $filename_img;
    }

    public function create_doughnut_image_base64($data, $colors, $text_total = "Detected", $x_text = 50)
    {
        $img = $this->create_doughnut_image($data, $colors, $text_total, $x_text);
        ob_start();
        imagepng($img);
        $image_data = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($image_data);
    }
}
