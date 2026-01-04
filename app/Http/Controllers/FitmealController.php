<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Midtrans\Config;
use Midtrans\Snap;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FitmealController extends Controller {

    public function __construct() {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    // --- AUTHENTICATION ---
    public function redirectToGoogle() {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback() {
        try {
            $gUser = Socialite::driver('google')->user();
            $user = User::updateOrCreate(['email' => $gUser->email], [
                'name' => $gUser->name,
                'google_id' => $gUser->id,
                'password' => bcrypt(Str::random(16))
            ]);
            Auth::login($user);
            return redirect('/dashboard');
        } catch (\Exception $e) {
            return redirect('/login');
        }
    }

    // --- USER DASHBOARD ---
    public function dashboard(Request $request) {
        $user = Auth::user();
        if ($user->role === 'admin') return redirect('/admin');

        if ($request->query('status_code') == '200' || $request->query('transaction_status') == 'settlement') {
            $user->update([
                'is_subscribed' => true,
                'subscription_end_date' => now()->addMonths(3)
            ]);
        }

        if ($user->is_subscribed && now() > $user->subscription_end_date) {
            $user->update(['is_subscribed' => false]);
        }

        $prof = json_decode($user->profile_data);
        $userBmr = $prof->bmr ?? 1500;

        $allPlans = DB::table('daily_plans')->get();

        if ($userBmr <= 1600) {
            $nutritionPlans = $allPlans->where('type', 'nutrition')
                                       ->where('calories', '<=', 500)
                                       ->shuffle()
                                       ->take(20);
        } else {
            $nutritionPlans = $allPlans->where('type', 'nutrition')
                                       ->where('calories', '>', 500)
                                       ->shuffle()
                                       ->take(20);
        }

        $workoutPlans = $allPlans->where('type', 'workout')->shuffle()->take(10);
        $plans = $nutritionPlans->merge($workoutPlans);

        return view('dashboard', compact('user', 'plans'));
    }

    // --- FITUR EDIT PROFIL MANDIRI (BARU) ---
    public function updateProfile(Request $request) {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profil Anda berhasil diperbarui!');
    }

    // --- BMI CALCULATION ---
    public function bmi(Request $req) {
        $req->validate(['weight' => 'required|numeric', 'height' => 'required|numeric']);

        $tb = $req->height / 100;
        $bmi = $req->weight / ($tb * $tb);
        $bmr = (10 * $req->weight) + (6.25 * $req->height) - (5 * 25) + 5;

        $protein = ($bmr * 0.25) / 4;
        $karbo = ($bmr * 0.50) / 4;
        $lemak = ($bmr * 0.25) / 9;

        Auth::user()->update(['profile_data' => json_encode([
            'weight' => $req->weight,
            'height' => $req->height,
            'bmi' => number_format($bmi, 1),
            'bmr' => round($bmr),
            'nutrisi' => [
                'protein' => round($protein, 1),
                'karbo' => round($karbo, 1),
                'lemak' => round($lemak, 1)
            ]
        ])]);

        return redirect('/dashboard')->with('success', 'Kalkulasi Berhasil! Menu Anda telah diperbarui.');
    }

    // --- PAYMENT ---
    public function subscribe() {
        $user = Auth::user();
        $orderId = 'FIT-' . time() . '-' . $user->id;
        $params = [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => 50000],
            'customer_details' => ['first_name' => $user->name, 'email' => $user->email],
            'callbacks' => ['finish' => route('dashboard'), 'error' => route('dashboard'), 'pending' => route('dashboard')]
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            DB::table('transactions')->insert([
                'user_id' => $user->id, 'order_id' => $orderId, 'amount' => 50000,
                'snap_token' => $snapToken, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()
            ]);
            return response()->json(['snap_token' => $snapToken]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $req) {
        $serverKey = config('services.midtrans.server_key');
        $hash = hash("sha512", $req->order_id.$req->status_code.$req->gross_amount.$serverKey);
        if ($hash == $req->signature_key) {
            if ($req->transaction_status == 'settlement' || $req->transaction_status == 'capture') {
                $trx = DB::table('transactions')->where('order_id', $req->order_id)->first();
                if ($trx) {
                    DB::table('transactions')->where('id', $trx->id)->update(['status' => 'success']);
                    User::find($trx->user_id)->update(['is_subscribed' => true, 'subscription_end_date' => now()->addMonths(3)]);
                }
            }
        }
    }

    // --- ADMIN PANEL ---
    public function admin() {
        $users = User::where('role', 'user')->get();
        $plans = DB::table('daily_plans')->orderBy('created_at', 'desc')->get();

        $visitors = DB::table('visitor_logs')
            ->select('visit_date', DB::raw('count(*) as total'))
            ->where('visit_date', '>=', now()->subDays(6))
            ->groupBy('visit_date')
            ->orderBy('visit_date', 'asc')
            ->get();

        $visitorData = [
            'labels' => $visitors->pluck('visit_date')->map(fn($d) => Carbon::parse($d)->translatedFormat('D')),
            'data' => $visitors->pluck('total')
        ];

        return view('admin', compact('users', 'plans', 'visitorData'));
    }

    public function storePlan(Request $req) {
        DB::table('daily_plans')->insert([
            'plan_date' => now(),
            'type' => $req->type,
            'category' => $req->category,
            'title' => $req->title,
            'description' => $req->description,
            'calories' => $req->calories,
            'instructions' => $req->instructions,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return back()->with('success', 'Data Berhasil Ditambahkan!');
    }

    public function updateUser(Request $req, $id) {
        $user = User::findOrFail($id);
        $user->update([
            'name' => $req->name,
            'email' => $req->email,
            'is_subscribed' => $req->is_subscribed == '1',
            'subscription_end_date' => $req->is_subscribed == '1' ? now()->addMonths(3) : null
        ]);
        return back()->with('success', 'Profil User Berhasil Diperbarui!');
    }

    public function deletePlan($id) {
        DB::table('daily_plans')->where('id', $id)->delete();
        return back()->with('success', 'Data Berhasil Dihapus!');
    }

    public function toggleUser($id) {
        $u = User::findOrFail($id);
        $u->update([
            'is_subscribed' => !$u->is_subscribed,
            'subscription_end_date' => !$u->is_subscribed ? null : now()->addMonths(3)
        ]);
        return back();
    }
}
