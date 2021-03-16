<?php namespace App\Http\Controllers;

use DB;
use App;
use Cache;
use Input;
use App\Genre;
use Carbon\Carbon;
use App\Services\Paginator;
use App\Services\Discover\LastfmGenres;

class GenreController extends Controller {

	/**
	 * Paginator Instance.
	 *
	 * @var Paginator
	 */
	private $paginator;

    /**
     * Last.fm genres service instance.
     *
     * @var LastfmGenres
     */
    private $lastfmGenres;

    /**
     * Settings service instance.
     *
     * @var App\Services\Settings
     */
    private $settings;

	public function __construct(Paginator $paginator, LastfmGenres $lastfmGenres)
	{
		$this->paginator = $paginator;
        $this->lastfmGenres = $lastfmGenres;
        $this->settings = App::make('Settings');
	}

	/**
	 * Get genres and artists related to it.
	 *
	 * @param string $names
	 * @return Collection
	 */
	public function getGenres($names)
	{
        if ($this->settings->get('genre_provider') === 'last.fm') {
            if ($homepageGenres = $this->settings->get('homepageGenres')) {
                return $this->lastfmGenres->formatGenres($homepageGenres)['formatted'];
            } else {
                return Cache::remember('last.fm'.$names, Carbon::now()->addDays(2), function() {
                    return $this->lastfmGenres->getMostPopular();
                });
            }
        } else {
            $names    = str_replace(', ', ',', $names);
            $orderBy  = implode(',', array_map(function($v) { return "'".$v."'"; }, explode(',', $names)));
            $cacheKey = 'genres.'.Input::get('limit', 20).$names;

            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $genres = Genre::whereIn('name', explode(',', $names))->orderByRaw(DB::raw("FIELD(name, $orderBy)"))->get();

            if ($genres->isEmpty()) {
                abort(404);
            }

            //limit actors loaded for genres
            $genres->map(function ($genre) {
                $genre->load(['artists' => function ($q) {
                    $q->limit(Input::get('limit', 20));
                }]);

                return $genre;
            });

            Cache::put($cacheKey, $genres, Carbon::now()->addDays(1));

            return $genres;
        }
	}

	/**
	 * Paginate given genres artists.
	 *
	 * @param string $name
	 * @return array
	 */
	public function paginateArtists($name)
	{
        $genres = $this->settings->get('homepageGenres');

        if ($genres) {
            $genres = array_map(function($genre) { return trim($genre); }, explode(',', $genres));
        }

        if ($genres && in_array($name, $genres)) {
            $genre = Genre::firstOrCreate(['name' => $name]);
        } else {
            $genre = Genre::where('name', $name)->firstOrFail();
        }

        if (App::make('Settings')->get('genre_provider') === 'last.fm') {
            Cache::remember($name.'artists', Carbon::now()->addDays(3), function() use ($genre) {
                return $this->lastfmGenres->getGenreArtists($genre);
            });
        }

        $input = Input::all(); $input['itemsPerPage'] = 20;
        $artists = $this->paginator->paginate($genre->artists(), $input, 'artists')->toArray();

        return ['genre' => $genre, 'artists' => $artists];
	}
}
