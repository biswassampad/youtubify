<?php namespace App\Http\Controllers;

use App;
use Auth;
use Cache;
use Input;
use Storage;
use Exception;
use App\Track;
use Carbon\Carbon;
use App\Http\Requests;
use App\Services\Paginator;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class TrackController extends Controller {

	/**
	 * Eloquent Track model instance.
	 *
	 * @var Track
	 */
	private $model;

	/**
	 * Paginator Instance.
	 *
	 * @var Paginator
	 */
	private $paginator;

	public function __construct(Track $track, Paginator $paginator)
	{
        $this->middleware('admin', ['only' => ['destroy', 'index', 'store']]);

        if (IS_DEMO) {
            $this->middleware('disableOnDemoSite', ['only' => ['destroy', 'store']]);
        }

		$this->model = $track;
		$this->paginator = $paginator;
		$this->settings  = App::make('Settings');
	}

	/**
	 * Return 50 most popular songs.
	 *
	 * @return mixed
	 */
	public function getTopSongs()
	{
        return Cache::remember('tracks.top50', Carbon::now()->addDays($this->settings->get('homepage_update_interval')), function() {
			if ($this->settings->get('top_songs_provider') === 'local') {
                return $this->model->with('album.artist')->orderBy('spotify_popularity', 'desc')->limit(50)->get();
            } else {
                return App::make('App\Services\Discover\SpotifyTopTracks')->get();
            }
		});
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		return $this->paginator->paginate($this->model->with('Album'), Input::all(), 'tracks');
	}

	/**
	 * Find track matching given id.
	 *
	 * @param  int  $id
	 * @return Track
	 */
	public function show($id)
	{
		return $this->model->with('album.artist')->findOrFail($id);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Track
	 */
	public function update($id)
	{
		$track = $this->model->findOrFail($id);

        //when admin is not logged in only youtube id can be changed
        if ( ! Auth::user() || ! Auth::user()->isAdmin) {
            $input = ['youtube_id' => Input::get('youtube_id')];
        } else {
            $input = Input::except('album');
        }

		if (isset($input['artists'])) {
			if (is_array($input['artists'])) {
				$input['artists'] = implode('*|*', $input['artists']);
			} else {
				$input['artists'] = str_replace(',', '*|*', $input['artists']);
			}
		}

		$track->fill($input)->save();

        $this->uploadTrack($track);

		return $track;
	}

    /**
     * Create new track.
     *
     * @param  int  $id
     * @return Track
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $this->validate($request, [
            'name'               => 'required|min:1|max:255',
            'number'             => 'required|min:1',
            'album_name'         => 'required|min:1|max:255',
            'spotify_popularity' => 'required|min:1|max:100',
            'album_id'           => 'required|min:1',
        ]);

        $track = Track::create(Input::except('file'));;

        $this->uploadTrack($track);

        return $track;
    }

	/**
	 * Remove tracks from database.
	 *
	 * @return mixed
	 */
	public function destroy()
	{
		if ( ! Input::has('items')) return;

        $ids = [];

        foreach (Input::get('items') as $track) {
            $ids[] = $track['id'];

            if ($track['url']) {
                try {
                    Storage::delete('application/storage/app/music/'.md5(Str::slug($track['artists'][0]).'_'.Str::slug($track['album_name']).'_'.Str::slug($track['name'])));
                } catch (Exception $e) { }
            }
        }

		if ($deleted = $this->model->destroy($ids)) {
			return response(trans('app.deleted', ['number' => $deleted]));
		}
	}

    /**
     * Upload track file and set model field to url.
     *
     * @param Track $model
     * @return Track
     */
    private function uploadTrack($model)
    {
        if (Input::hasFile('file')) {
            $file   = Input::file('file');
            $path   = 'application/storage/app/music/'.md5(Str::slug($model->artists[0]).'_'.Str::slug($model->album_name).'_'.Str::slug($model->name));

            if (Storage::put($path, file_get_contents($file))) {
                $model->url = url('track/'.$model->id.'/'.$file->getClientOriginalExtension().'/stream');
                $model->save();
            }
        }

        return $model;
    }
}
