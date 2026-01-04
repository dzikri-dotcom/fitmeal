<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-bold text-2xl text-gray-800 leading-tight">Dashboard</h2>
            <div class="flex items-center space-x-4">
                <button onclick="document.getElementById('profile-modal').classList.remove('hidden')" class="text-sm text-blue-600 hover:underline">
                    ⚙️ Edit Profil
                </button>

                @if(Auth::user()->is_subscribed)
                    <span class="bg-emerald-100 text-emerald-700 px-4 py-1 rounded-full text-sm font-bold border border-emerald-200">✨ Premium</span>
                @endif
            </div>
        </div>
    </x-slot>

    <script src="{{ config('services.midtrans.snap_url') }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-500 text-white rounded-lg shadow-md">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-500 text-white rounded-lg shadow-md">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(Auth::user()->is_subscribed)
                @include('dashboard-parts.premium')
            @else
                @include('dashboard-parts.free')
            @endif

        </div>
    </div>

    <div id="profile-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white p-8 rounded-2xl shadow-xl max-w-md w-full">
            <h3 class="text-xl font-bold mb-4">Edit Profil Anda</h3>

            <form action="{{ route('profile.custom_update') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" name="name" value="{{ Auth::user()->name }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Alamat Email</label>
                        <input type="email" name="email" value="{{ Auth::user()->email }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('profile-modal').classList.add('hidden')" class="px-4 py-2 text-gray-600 hover:text-gray-800">Batal</button>
                    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg font-bold hover:bg-emerald-700 shadow-lg">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('pay-btn')?.addEventListener('click', function () {
            fetch('{{ route("pay") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
            })
            .then(r => r.json()).then(d => {
                if(d.snap_token) {
                    window.snap.pay(d.snap_token);
                } else {
                    alert("Gagal mendapatkan token pembayaran");
                }
            });
        });
    </script>
</x-app-layout>
