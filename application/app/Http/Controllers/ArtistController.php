<?php namespace App\Http\Controllers;

use App;
use Input;
use Storage;
use App\Genre;
use App\Track;
use App\Artist;
use Carbon\Carbon;
use App\Services\ArtistBio;
use App\Services\Paginator;
use Illuminate\Support\Str;
use App\Services\Artist\ArtistSaver;
use App\Services\Artist\SpotifyArtist as Provider;

class ArtistController extends Controller {

	/**
	 * External artist provider service.
	 *
	 * @var Provider
	 */
	private $provider;

	/**
	 * Artist db saver instance.
	 *
	 * @var \App\Services\Artist\ArtistSaver
	 */
	private $saver;

	/**
	 * Paginator Instance.
	 *
	 * @var Paginator
	 */
	private $paginator;

	/**
	 * Create new ArtistController instance.
	 *
	 * @param Provider $provider
	 */
	public function __construct(Provider $provider, ArtistSaver $saver, Paginator $paginator, Artist $model, ArtistBio $artistBio)
	{
		$this->middleware('admin', ['only' => ['destroy', 'index', 'update', 'store']]);

        if (IS_DEMO) {
            $this->middleware('disableOnDemoSite', ['only' => ['destroy', 'update', 'store']]);
        }

		$this->provider  = $provider;
		$this->saver     = $saver;
		$this->paginator = $paginator;
		$this->model     = $model;
		$this->settings  = App::make('Settings');
        $this->artistBio = $artistBio;
	}

	/**
	 * Paginate all artists.
	 *
	 * @return Collection
	 */
	public function index()
	{
		return $this->paginator->paginate($this->model->with('albums.tracks'), Input::all(), 'artists');
	}

	/**
	 * Update artist.
	 *
	 * @param  int  $id
	 * @return Artist
	 */
	public function update($id)
	{
		$artist = $this->model->findOrFail($id);

        foreach(($input = Input::all()) as $key => $value) {
            if ($key === 'genres') {
                if ( ! is_array($value)) $value = [];


                $currentArtistGenres = array_map(function($g) { return $g['name']; }, $artist->genres->toArray());
                $newArtistGenres = $value;

                //detach genres
                foreach($currentArtistGenres as $genre) {
                    if ( ! in_array($genre, $newArtistGenres)) {
                        Genre::where('name', $genre)->first()->artists()->detach($artist['id']);
                    }
                }

                //attach new genres
                foreach($newArtistGenres as $genre) {
                    if ( ! in_array($genre, $currentArtistGenres)) {
                        $genre = Genre::firstOrCreate(['name' => $genre]);
                        $genre->artists()->attach($artist['id']);
                    }
                }

                unset($input[$key]);
            } else if (is_array($value)) {
                unset($input[$key]);
            }
        }

		$artist->fill($input)->save();

		return $artist;
	}

    /**
     * Create a new artist.
     *
     * @return Artist
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $this->validate($request, [
            'name' => 'required|min:1|max:255|unique:artists'
        ]);

        $artist = Artist::create(array_except(Input::all(), 'genres'));

        if (($genres = Input::get('genres')) && is_array($genres)) {
            foreach($genres as $genre) {
                $genre = Genre::firstOrCreate(['name' => $genre]);
                $genre->artists()->attach($artist->id);
            }
        }

        return $artist;
    }

    /**
     * Upload new image for specified artist and add link to it on artist model.
     *
     * @param $id
     */
    public function uploadImage($id, \Illuminate\Http\Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:jpeg,png,jpg',
            'type' => 'required|min:10'
        ]);

        $file   = Input::file('file');
        $artist = Artist::findOrFail($id);
        $type   = Input::get('type');
        $end    = 'assets/images/artists/'.md5(Str::slug($artist->name).'_'.$type).'.'.$file->getClientOriginalExtension();

        if (Storage::put(base_path('../'.$end), file_get_contents($file))) {
            $artist->$type = url($end);
            $artist->save();
        }

        return $artist;
    }

	/**
	 * Return 30 most popular artists (by spotify popularity).
	 *
	 * @return Collection
	 */
	public function getMostPopularArtists()
	{
		return $this->model->orderBy('spotify_popularity', 'desc')->limit(30)->get();
	}

	/**
	 * Return artist data from db or 3rd party service.
	 *
	 * @return Artist
	 */
	public function getArtist()
	{
		if (Input::get('name') === 'Various Artists') abort(404);

        $name = preg_replace('!\s+!', ' ', Input::get('name'));

        $existing = $this->model->with('albums')->where('name', $name)->first();

        if ($this->settings->get('data_provider', 'spotify') !== 'local' && (! $existing || ! $existing->fully_scraped || $existing->albums->isEmpty()
            || $existing->updated_at->addDays($this->settings->get('artist_update_interval')) <= Carbon::now())) {

			$artist = $this->provider->getArtist($name);

			//if provider couldn't find artist, bail with 404
			if ( ! $artist && ! Input::get('force')) {
                abort(404);
            } else if ($artist) {
                $existing = $this->saver->save($artist);
            }
		}

		$artist = $existing->load('albums.tracks', 'similar', 'genres');

        if (Input::get('top-tracks')) {
            $artist->topTracks = $this->getTopTracks($name);
        }

        return $artist;
	}

	/**
	 * Get 20 most popular artists tracks.
	 *
	 * @return Collection
	 */
	public function getTopTracks($name)
	{
		$tracks = Track::with('album.artist')
			->where('artists', 'like', $name.'%')
			->orderBy('spotify_popularity', 'desc')
			->limit(20)
			->get();

		return $tracks;
	}

    /**
     * Get artists biography and images from external sites.
     *
     * @param string $name
     * @return string
     */
    public function getBio($id)
    {
        return $this->artistBio->get($id, Input::get('name'));
    }

	/**
	 * Remove artists from database.
	 *
	 * @return mixed
	 */
	public function destroy()
	{
		if ( ! Input::has('items')) return;

		foreach (Input::get('items') as $artist) {
            $artist = Artist::find($artist['id']);

			if ($artist) {
                foreach($artist->albums as $album) {
                    $album->tracks()->delete();
                }

                $artist->albums()->delete();
                $artist->delete();
            }
		}

		return response(trans('app.deleted', ['number' => count(Input::get('items'))]));
	}

}
