<?php

namespace Tests\Support;

/**
 * Synthetic pages that mirror the public KKTCarabam listing markup. All values
 * are invented test data; no real seller data or descriptions are stored.
 */
final class KktFixture
{
    public static function listingHtml(array $o = []): string
    {
        $o += [
            'id' => '900001', 'year' => 2020, 'title_year' => null, 'make' => 'Toyota', 'model' => 'Corolla', 'variant' => null,
            'category' => 'Otomobil', 'price' => '12.500 GBP', 'try' => '834.000,00 TL', 'km' => '45000',
            'fuel' => 'Benzin', 'gear' => 'Otomatik', 'title_gear' => 'Otomatik', 'body' => 'Sedan', 'color' => 'Beyaz',
            'steering' => 'Sağ Direksiyon', 'cc' => '1600', 'hp' => '120', 'condition' => '2.El (Plakalı)',
            'date' => '10 Kasım 2026', 'city' => 'Lefkoşa', 'district' => 'Gönyeli', 'images' => 6,
            'seller' => 'Test Seller', 'dealer' => false, 'phone' => '0533 *** ** **',
            'description' => ['Test açıklama satırı', 'Geri Görüş Kamerası', 'Cruise Control', 'Koltuk Isıtma'],
        ];
        $tYear = $o['title_year'] ?? $o['year'];
        $imgs = '';
        for ($i = 1; $i <= $o['images']; $i++) {
            $imgs .= "<a href=\"https://www.kktcarabam.com/uploads/images/2026/11/10/10/img-{$o['id']}-{$i}-900_675.jpg\"><img src=\"https://www.kktcarabam.com/uploads/images/2026/11/10/10/img-{$o['id']}-{$i}-200_140.jpg\"></a>\n";
        }
        $variant = $o['variant'] ? "<li class=\"breadcrumb-item\"><a href=\"#\">{$o['variant']}</a></li>" : '';
        $desc = implode('', array_map(fn ($l) => "<p><strong>{$l}</strong></p>", $o['description']));
        $profile = $o['dealer'] ? '<a href="https://www.kktcarabam.com/uye/test-motors">Galeri</a>' : '';
        $rows = '';
        foreach (['İlan No' => $o['id'], 'İlan Tarihi' => $o['date'], 'Araç Durumu' => $o['condition'], 'Kasa Tipi' => $o['body'],
            'Yıl' => $o['year'], 'Kilometre' => $o['km'], 'Yakıt Türü' => $o['fuel'], 'Vites Tipi' => $o['gear'], 'Renk' => $o['color'],
            'Direksiyon Tipi' => $o['steering'], 'Motor Hacmi (cc)' => $o['cc'], 'Motor Gücü (hp)' => $o['hp']] as $k => $v) {
            if ($v !== null) {
                $rows .= "<tr><th>{$k}:</th><td>{$v}</td></tr>";
            }
        }
        $prices = $o['price']
            ? '<div class="swiper-slide"><div class="fiyat gbp">'.$o['price'].'</div></div>'
                .($o['try'] ? '<div class="swiper-slide"><div class="fiyat tl">'.$o['try'].'</div></div>' : '')
            : '';

        return '<!doctype html><html><head><meta property="og:image" content="https://www.kktcarabam.com/uploads/images/x-900_675.jpg"></head><body>'
            .'<div class="sidebar"><div class="fiyat">1 GBP</div></div>'
            .'<ol class="breadcrumb"><li class="breadcrumb-item"><a href="#"><i></i></a></li><li class="breadcrumb-item"><a href="#">2. El Araçlar</a></li>'
            ."<li class=\"breadcrumb-item\"><a href=\"#\">{$o['category']}</a></li><li class=\"breadcrumb-item\"><a href=\"#\">{$o['make']}</a></li>"
            ."<li class=\"breadcrumb-item\"><a href=\"#\">{$o['model']}</a></li>{$variant}</ol>"
            ."<h1>{$tYear} Model {$o['title_gear']} {$o['make']} {$o['model']}</h1>"
            ."<div class=\"fotorama\">{$imgs}</div>"
            ."<div class=\"orta bg-white\"><div class=\"fiyat_bilgi swiper\"><div class=\"swiper-wrapper\">{$prices}</div></div>"
            ."<div class=\"adres_bilgi\"><a href=\"#\">{$o['city']}</a> / <a href=\"#\">{$o['district']}</a></div>"
            ."<div class=\"table-responsive\"><table class=\"table\"><tbody>{$rows}</tbody></table></div></div>"
            ."<div class=\"card style-2\"><div class=\"card-header\">Açıklama</div><div class=\"card-body\">{$desc}</div></div>"
            ."<div class=\"sag iletisim bg-white\"><h4>{$o['seller']}</h4>{$profile}<a class=\"iletisim\"><span class=\"telefon tel-no\">{$o['phone']}</span></a></div>"
            .'</body></html>';
    }

    /** Distinct JPEG bytes per seed (GD). */
    public static function jpeg(int $seed, int $w = 640, int $h = 480): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $seed * 37 % 256, $seed * 71 % 256, $seed * 113 % 256));
        imagestring($im, 5, 10, 10, "seed {$seed}", imagecolorallocate($im, 255, 255, 255));
        ob_start();
        imagejpeg($im, null, 80);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** A varied pool of $n valid listings. */
    public static function pool(int $n = 26): array
    {
        $makes = [['Toyota', 'Corolla'], ['Toyota', 'Yaris'], ['Toyota', 'RAV4'], ['Nissan', 'Note'], ['Nissan', 'Juke'], ['Mazda', 'CX-5'],
            ['Mazda', 'Axela'], ['Honda', 'Fit'], ['Honda', 'Vezel'], ['BMW', '3 Serisi'], ['Mercedes - Benz', 'C Serisi'], ['Suzuki', 'Swift'],
            ['Volkswagen', 'Golf'], ['Hyundai', 'Tucson'], ['Kia', 'Sportage'], ['Ford', 'Focus'], ['Audi', 'A3'], ['Peugeot', '208'],
            ['Renault', 'Clio'], ['Lexus', 'NX'], ['Subaru', 'XV'], ['Mitsubishi', 'Outlander'], ['Land Rover', 'Evoque'], ['Mini', 'Cooper'],
            ['Citroen', 'C3'], ['Fiat', '500']];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            [$make, $model] = $makes[$i % count($makes)];
            $price = 4000 + $i * 1700;
            $out[] = ['id' => (string) (910000 + $i), 'make' => $make, 'model' => $model, 'year' => 2012 + $i % 12,
                'price' => number_format($price, 0, ',', '.').' GBP', 'try' => number_format($price * 66.75, 2, ',', '.').' TL',
                'km' => (string) (20000 + $i * 3100), 'images' => 5 + $i % 4];
        }

        return $out;
    }

    public static function adapter(array $listings, array $failImagesFor = [], bool $blocked = false, array $gone = []): FixtureAdapter
    {
        return new FixtureAdapter($listings, $failImagesFor, $blocked, $gone);
    }
}
