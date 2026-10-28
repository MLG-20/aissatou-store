<?php

namespace Tests\Unit;

use App\Support\YouTube;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YouTubeTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function links(): array
    {
        return [
            'short' => ['https://youtube.com/shorts/aBcDeFgHiJ0?si=Xy12', 'aBcDeFgHiJ0'],
            'vidéo' => ['https://www.youtube.com/watch?v=aBcDeFgHiJ0&t=12s', 'aBcDeFgHiJ0'],
            'vidéo, v après un autre paramètre' => ['https://www.youtube.com/watch?feature=share&v=aBcDeFgHiJ0', 'aBcDeFgHiJ0'],
            'lien court' => ['https://youtu.be/aBcDeFgHiJ0?si=abc', 'aBcDeFgHiJ0'],
            'mobile' => ['https://m.youtube.com/shorts/aBc-eF_HiJ0', 'aBc-eF_HiJ0'],
            'intégration' => ['https://www.youtube-nocookie.com/embed/aBcDeFgHiJ0', 'aBcDeFgHiJ0'],
            'sans https' => ['youtube.com/shorts/aBcDeFgHiJ0', 'aBcDeFgHiJ0'],
            'TikTok' => ['https://www.tiktok.com/@boutique/video/1234567890', null],
            'faux domaine' => ['https://youtube.com.example.org/watch?v=aBcDeFgHiJ0', null],
            'identifiant trop long' => ['https://youtu.be/aBcDeFgHiJ0x', null],
            'texte' => ['ma vidéo', null],
        ];
    }

    #[DataProvider('links')]
    public function test_the_video_id_is_read_from_any_youtube_link(string $url, ?string $expected): void
    {
        $this->assertSame($expected, YouTube::idFrom($url));
    }

    public function test_the_player_is_muted_looping_and_cookie_free(): void
    {
        $url = YouTube::embedUrl('aBcDeFgHiJ0');

        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/aBcDeFgHiJ0?', $url);
        $this->assertStringContainsString('mute=1', $url);
        $this->assertStringContainsString('loop=1&playlist=aBcDeFgHiJ0', $url);
        $this->assertStringContainsString('rel=0', $url);
    }
}
