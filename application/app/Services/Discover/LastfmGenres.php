<?php namespace App\Services\Discover;

use App;
use App\Album;
use App\Artist;
use Illuminate\Support\Str;
use App\Services\Artist\ArtistSaver;
use App\Services\Artist\SpotifyArtist;
use App\Services\HttpClient;
use Illuminate\Support\Collection;

class LastfmGenres {

    /**
     * HttpClient instance.
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Create new LastfmGenres instance.
     */
    public function __construct(SpotifyArtist $spotifyArtist, ArtistSaver $saver)
    {
        $this->httpClient    = new HttpClient(['base_url' => 'http://ws.audioscrobbler.com/2.0/']);
        $this->spotifyArtist = $spotifyArtist;
        $this->saver         = $saver;
        $this->apiKey        = App::make('Settings')->get('lastfm_api_key');

        ini_set('max_execution_time', 0);
    }

    public function getMostPopular()
    {
        $response = $this->httpClient->get("?method=tag.getTopTags&api_key=$this->apiKey&format=json");

        $formatted = $this->formatGenres($response['toptags']['tag']);

        $this->saver->saveOrUpdate($formatted['names'], array_flatten($formatted['names']), 'genres');

        return $formatted['formatted'];
    }

    public function formatGenres($genres) {
        $formatted = [];
        $names     = [];

        if (is_string($genres)) {
            $genres = explode(',', $genres);
        }

        foreach($genres as $genre) {
            if (is_array($genre)) {
                $formatted[] = ['name' => $genre['name'], 'popularity' => $genre['count'], 'image' => $this->getLocalImagePath($genre['name'])];
                $names[] = ['name' => $genre['name']];
            } else {
                $genre = trim($genre);
                $formatted[] = ['name' => $genre, 'popularity' => 0, 'image' => $this->getLocalImagePath($genre)];
                $names = $genres;
            }
        }

        return ['formatted' => $formatted, 'names' => $names];
    }

    public function getGenreArtists($genre)
    {
        $genreName = $genre['name'];
        $response  = $this->httpClient->get("?method=tag.gettopartists&tag=$genreName&api_key=$this->apiKey&format=json&limit=50");
        $artists   = $response['topartists']['artist'];
        $names     = [];
        $formatted = [];

        foreach($artists as $artist) {
            if ( ! $this->collectionContainsArtist($artist['name'], $formatted)) {
                $formatted[] = [
                    'name' => $artist['name'],
                    'image_small' => $artist['image'][4]['#text'],
                    'fully_scraped' => 0,
                ];

                $names[] = $artist['name'];
            }
        }

        $existing = Artist::whereIn('name', $names)->get();

        $insert = array_filter($formatted, function($artist) use ($existing) {
            return ! $this->collectionContainsArtist($artist['name'], $existing);
        });

        Artist::insert($insert);

        $artists = Artist::whereIn('name', $names)->get();

        $this->attachGenre($artists, $genre);

        return $artists;
    }

    /**
     * Attach genre to artists in database.
     *
     * @param Collection $artists
     * @param App\Genre $genre
     */
    private function attachGenre($artists, $genre)
    {
        $pivotInsert = [];

        foreach ($artists as $artist) {
            $pivotInsert[] = ['genre_id' => $genre['id'], 'artist_id' => $artist['id']];
        }

        $this->saver->saveOrUpdate($pivotInsert, array_flatten($pivotInsert), 'genre_artist');
    }

    private function getLocalImagePath($genreName)
    {
        $genreName = str_replace(' ', '-', strtolower(trim($genreName)));

        $end = 'assets/images/genres/'.$genreName.'.jpg';

        return App::make('Settings')->get('enable_https') ? secure_url($end) : url($end);
    }

    private function collectionContainsArtist($name, $collection) {
        foreach ($collection as $artist) {
            $needle = Str::slug($name);
            $artistName = Str::slug($artist['name']);

            if (( ! $needle || ! $artistName) || $needle == $artistName) {
                return true;
            }
        }

        return false;
    }
}