<?php namespace App\Http\Controllers;

use App;
use Auth;
use Mail;
use Input;
use App\User;
use Exception;
use App\Services\Registrar;
use App\Http\Requests\LogUserInRequest;

class AuthController extends Controller {

    /**
     * Register/create a new user.
     *
     * @param Registrar $registrar
     * @return *
     */
    public function postRegister(Registrar $registrar)
	{
        //make sure that admin has enabled regisration before proceeding
        if ( ! App::make('Settings')->get('enableRegistration', true) && ( ! Auth::check() || ! Auth::user()->isAdmin)) {
            return response(trans('app.registrationDisabled'), 403);
        }

        $validator = $registrar->validator(Input::all());

        if ($validator->fails())
        {
            return response()->json($validator->errors(), 400);
        }

        $input = Input::all();
        $needsConfirmation = App::make('Settings')->get('require_email_confirmation', false);

        if ($needsConfirmation) {
            $code = str_random(30);
            $input['confirmation_code'] = $code;
            $input['confirmed'] = 0;
        }

        $user = $registrar->create($input);

        if ($needsConfirmation) {
            Mail::send('emails.confirmation', ['code' => $code], function($message) {
                $message->to(Input::get('email'))
                        ->subject(trans('app.verifyEmailSubject').' '.App::make('Settings')->get('siteName'));
            });
        }

        //if user is not logged in, do it now
        if ( ! Auth::check() && ! $needsConfirmation) {
            Auth::login($user);
        }

        return $user;
	}

    /**
     * Login in a user.
     *
     * @param LogUserInRequest $request
     * @return Response
     */
    public function postLogin(LogUserInRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (App::make('Settings')->get('require_email_confirmation', false)) {
            $credentials['confirmed'] = 1;
        }

        try {
            $attemptSuccessful = Auth::attempt($credentials, $request->get('remember'));
        } catch (Exception $e) {
            $attemptSuccessful = false;
        }

        if ($attemptSuccessful) {
            return response()->json(Auth::user(), 200);
        }

        return response()->json(array('*' => trans('app.wrongCredentials')), 422);
    }

    public function postLogout()
    {
        if (Auth::check()) {
            Auth::logout();
            return;
        }

        abort(404);
    }

    /**
     * Confirm users email address.
     *
     * @param string $confirmation_code
     * @return mixed
     */
    public function verifyEmail($code)
    {
        if ( ! $code) {
            return redirect('/');
        }

        $user = User::where('confirmation_code', $code)->firstOrFail();

        $user->confirmed = 1;
        $user->confirmation_code = null;
        $user->save();

        Auth::login($user);

        return redirect('/')->with('jsMessage', trans('app.emailConfirmSuccess'));
    }
}
