<?php

namespace App\Livewire\Buyer;

use App\Models\IdentityVerification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class IdentityVerificationFlow extends Component
{
    use WithFileUploads;

    // View State: 'intro', 'method', 'id_type', 'upload', 'camera', 'review', 'submitted'
    public string $currentScreen = 'intro';

    public ?string $method = null; // 'upload' or 'camera'
    public ?string $id_type = null; // 'drivers_license', 'passport', 'identity_card'

    // Files (temporary uploads via Livewire)
    public $front_image;
    public $back_image;

    protected function rules(): array
    {
        $rules = [
            'id_type' => ['required', 'in:drivers_license,passport,identity_card'],
            'method'  => ['required', 'in:upload,camera'],
            'front_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'],
        ];

        if ($this->id_type !== 'passport') {
            $rules['back_image'] = ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'];
        }

        return $rules;
    }

    public function mount(): void
    {
        $existing = Auth::user()->identityVerification;
        if ($existing && $existing->status === 'verified') {
            redirect()->route('user.account-management');
        }
    }

    public function start(): void
    {
        $this->currentScreen = 'method';
    }

    public function selectMethod(string $method): void
    {
        $this->method = $method;
        $this->currentScreen = 'id_type';
    }

    public function selectIdType(string $type): void
    {
        $this->id_type = $type;
        $this->currentScreen = $this->method === 'camera' ? 'camera' : 'upload';
    }

    public function proceedToReview(): void
    {
        $this->validate([
            'front_image' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'],
            'back_image'  => $this->id_type === 'passport' ? ['nullable'] : ['required', 'image', 'mimes:jpeg,jpg,png', 'max:10240'],
        ], [
            'front_image.required' => 'Please provide the front of your document.',
            'back_image.required'  => 'Please provide the back of your document.',
        ]);

        $this->currentScreen = 'review';
    }

    public function submit(): void
    {
        $this->validate();

        $user = Auth::user();
        $frontPath = $this->front_image->store('identity_documents/fronts', 'public');
        $backPath = ($this->id_type !== 'passport' && $this->back_image)
            ? $this->back_image->store('identity_documents/backs', 'public')
            : null;

        IdentityVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id_type'          => $this->id_type,
                'method'           => $this->method,
                'front_image_path' => $frontPath,
                'back_image_path'  => $backPath,
                'status'           => 'pending',
                'rejection_reason' => null,
                'verified_at'      => null,
            ]
        );

        $this->currentScreen = 'submitted';
    }

    public function render()
    {
        return view('livewire.buyer.identity-verification-flow')
                ->layout('layouts.app', ['hideFooter' => true]);
    }
}