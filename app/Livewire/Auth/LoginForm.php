<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Livewire\Component;

class LoginForm extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    // Portal UI configuration passed from the parent view
    public string $title = 'Sign In';
    public string $subtitle = 'Enter your credentials to access your account';
    public string $expectedRole = 'Buyer';
    public ?string $registerRoute = null;
    public string $registerText = 'Create an account';
    public bool $showRegister = true;

    public function mount(
        string $title = 'Sign In',
        string $subtitle = 'Enter your credentials to access your account',
        string $expectedRole = 'Buyer',
        ?string $registerRoute = null,
        string $registerText = 'Create an account',
        bool $showRegister = true
    ): void {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->expectedRole = $expectedRole;
        $this->registerRoute = $registerRoute ?? route('register');
        $this->registerText = $registerText;
        $this->showRegister = $showRegister;
    }

    /**
     * Real-time format validation as the user inputs data.
     * Evaluates format only (syntax & length) to protect against email enumeration.
     */
    public function updated($propertyName): void
    {
        if ($propertyName === 'email') {
            $this->validateOnly('email', [
                'email' => ['required', 'email:rfc,dns'],
            ], [
                'email.required' => 'Please enter your email address.',
                'email.email' => 'Please enter a valid email address.',
            ]);
        } elseif ($propertyName === 'password') {
            $this->validateOnly('password', [
                'password' => ['required', 'min:8'],
            ], [
                'password.required' => 'Please enter your password.',
                'password.min' => 'Password must be at least 8 characters.',
            ]);
        }
    }

    /**
     * Authenticate and enforce portal role matching on form submission.
     */
    public function submit(AuthService $authService, Request $request)
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Synthesize an authentication request for the common AuthService
        $syntheticRequest = Request::create(
            request()->getUri(),
            'POST',
            [
                'email' => $this->email,
                'password' => $this->password,
                'remember' => $this->remember,
            ]
        );
        $syntheticRequest->setLaravelSession(session());

        try {
            return $authService->authenticate($syntheticRequest, $this->expectedRole);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}