<h2>Verify Your Email Address</h2>

<div>
    Thanks for creating an account with {{ App::make('Settings')->get('siteName') }}
    Please follow the link below to verify your email address
    {{ url('register/verify/' . $code) }}<br/>
</div>