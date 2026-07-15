@extends('layouts.shop')

@section('content')
    @php($accountActive = trim($__env->yieldContent('accountActive', 'profile')))
    <section class="py-5 bg-vn-soft">
        <div class="container">
            <div class="account-shell">
                <x-account.sidebar :active="$accountActive" />
                <div>
                    @yield('accountContent')
                </div>
            </div>
        </div>
    </section>
@endsection
