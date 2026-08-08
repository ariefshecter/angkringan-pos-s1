@if(session('success'))
    <div data-app-flash data-type="success" class="hidden">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div data-app-flash data-type="error" class="hidden">{{ session('error') }}</div>
@endif
