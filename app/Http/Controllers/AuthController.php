<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Services\BrevoMailService;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Đăng ký
    public function register(Request $request)
    {
        // Validate dữ liệu
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:6',
                'max:50',
                'regex:/^\S*$/' 
            ],     
        ], [
            'username.unique' => 'Tên đăng nhập đã được sử dụng',
            'email.unique' => 'Email đã được đăng ký',
            'email.email' => 'Định dạng email không hợp lệ',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',
            'password.max' => 'Mật khẩu phải có tối đa 50 ký tự',
            'password.regex' => 'Mật khẩu không được chứa khoảng trắng'
        ]);

        // Tạo user mới
        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Laravel tự hash nhờ mutator
            'role' => 0, // mặc định
            'id_admin' => null, // mặc định
            'id_topic' => null, // mặc định
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký thành công!'
        ], 201);
    }

    // Đăng nhập
public function login(Request $request)
{
    $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);

    // Lấy username và chuyển về dạng thường để so sánh
    $username = trim($request->username);
    
    // Tìm user với username KHÔNG PHÂN BIỆT HOA/THƯỜNG
    $user = User::whereRaw('LOWER(username) = ?', [strtolower($username)])->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'username' => ['Sai tài khoản hoặc mật khẩu'],
        ]);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Login successful',
        'id' => $user->id,
        'email' => $user->email,
        'username' => $user->username, // Trả về nguyên dạng đã lưu
        'role' => (int) $user->role,
        'id_admin' => $user->id_admin ?? '',
        'id_topic' => $user->id_topic ?? '',
    ]);
}

    //đổi mật khẩu
public function changePassword(Request $request)
{
    $request->validate([
        'id' => 'required|exists:users,id',
        'old_password' => 'required|string',
        'password' => [
            'required',
            'string',
            'min:6',
            'max:50',
            'confirmed',
            'regex:/^\S*$/' 
        ],
    ], [
        'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',
        'password.max' => 'Mật khẩu không được vượt quá 50 ký tự',
        'password.confirmed' => 'Xác nhận mật khẩu không khớp',
        'password.regex' => 'Mật khẩu không được chứa khoảng trắng'
    ]);

    $user = User::findOrFail($request->id);

    if (! Hash::check($request->old_password, $user->password)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Mật khẩu cũ không chính xác.'
        ], 422);
    }

    $user->password = $request->password;
    $user->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Đổi mật khẩu thành công!'
    ]);
}

    // 1. Gửi mã đặt lại mật khẩu
public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ], [
        'email.exists' => 'Email này chưa được đăng ký.',
    ]);

    $token = rand(100000, 999999);
    $expiresAt = \Carbon\Carbon::now()->addMinutes(10);

    User::where('email', $request->email)->update([
        'reset_token' => $token,
        'reset_expires_at' => $expiresAt,
    ]);

    // Gửi email qua Brevo API
    $mailService = new BrevoMailService();
    $mailService->send(
        $request->email,
        'User',
        'Mã đặt lại mật khẩu',
        "Mã xác nhận đặt lại mật khẩu của bạn là: {$token}. Mã có hiệu lực trong 10 phút."
    );

    return response()->json([
        'status' => 'success',
        'message' => 'Mã xác nhận đã được gửi đến email của bạn.'
    ]);
}

// 2. Xác minh mã
public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
        'otp' => 'required|numeric|digits:6',
    ]);

    $user = User::where('email', $request->email)
        ->where('reset_token', $request->otp)
        ->where('reset_expires_at', '>', now())
        ->first();

    if (! $user) {
        return response()->json([
            'status' => 'error',
            'message' => 'Mã xác nhận không hợp lệ hoặc đã hết hạn.'
        ], 422);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Mã xác nhận hợp lệ.'
    ]);
}

// 3. Đặt lại mật khẩu
public function resetPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
        'otp' => 'required|numeric|digits:6',
        'password' => [
            'required',
            'string',
            'min:6',
            'max:50',
            'confirmed',
            'regex:/^\S*$/' 
        ],
    ], [
        'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',
        'password.max' => 'Mật khẩu không được vượt quá 50 ký tự',
        'password.confirmed' => 'Xác nhận mật khẩu không khớp',
        'password.regex' => 'Mật khẩu không được chứa khoảng trắng'
    ]);

    $user = User::where('email', $request->email)
        ->where('reset_token', $request->otp)
        ->where('reset_expires_at', '>', now())
        ->first();

    if (! $user) {
        return response()->json([
            'status' => 'error',
            'message' => 'Mã xác nhận không hợp lệ hoặc đã hết hạn.'
        ], 422);
    }

    // Cập nhật mật khẩu & xóa token
    $user->password = $request->password; // Auto-bcrypt nhờ mutator
    $user->reset_token = null;
    $user->reset_expires_at = null;
    $user->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Đổi mật khẩu thành công!'
    ]);
}
}