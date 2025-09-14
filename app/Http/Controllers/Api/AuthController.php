<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * AuthController - Kullanıcı Kimlik Doğrulama Controller'ı
 *
 * Bu controller JWT tabanlı kimlik doğrulama işlemlerini yönetir:
 * - Yeni kullanıcı kaydı
 * - Kullanıcı girişi
 * - Profil görüntüleme ve güncelleme
 * - Güvenli çıkış işlemi
 */
class AuthController extends Controller
{
    /**
     * Yeni kullanıcı kayıt işlemi
     *
     * Yeni bir kullanıcı hesabı oluşturur ve otomatik olarak JWT token üretir.
     * Kullanıcı kayıt olduktan sonra tekrar giriş yapmasına gerek kalmaz.
     *
     * @param Request $request - Kullanıcı bilgilerini içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // Gelen verilerin doğruluğunu kontrol et
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',                    // En az 2 karakter
            'email' => 'required|string|email|unique:users',      // Benzersiz email
            'password' => 'required|string|min:8'                 // En az 8 karakter
        ]);

        // Validasyon hatası varsa hata mesajı döndür
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        // Yeni kullanıcı oluştur
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),    // Şifreyi hashle
            'role' => 'user'                                 // Varsayılan rol
        ]);

        // Kullanıcı için JWT token oluştur
        $token = JWTAuth::fromUser($user);

        // Başarılı kayıt yanıtı
        return response()->json([
            'success' => true,
            'message' => 'İşlem Başarılı!',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }

    /**
     * Kullanıcı giriş işlemi
     *
     * Email ve şifre ile kullanıcı girişi yapar ve JWT token üretir.
     * Token, sonraki isteklerde kimlik doğrulaması için kullanılır.
     *
     * @param Request $request - Email ve şifre bilgilerini içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Sadece email ve password bilgilerini al
        $credentials = $request->only('email', 'password');

        try {
            // JWT ile giriş denemesi yap
            if (!$token = JWTAuth::attempt($credentials)) {
                // Kimlik bilgileri yanlışsa hata döndür
                return response()->json([
                    'success' => false,
                    'message' => 'Giriş Bilgileri Hatalı'
                ], 401);
            }
        } catch (JWTException $e) {
            // JWT token oluşturma hatası
            return response()->json([
                'success' => false,
                'message' => 'Token Oluşturulamadı'
            ], 500);
        }

        // Başarılı giriş yanıtı
        return response()->json([
            'success' => true,
            'message' => 'Giriş Başarılı!',
            'data' => [
                'token' => $token,
                'user' => JWTAuth::user()    // Giriş yapan kullanıcının bilgileri
            ]
        ]);
    }

    /**
     * Kullanıcı profil bilgilerini görüntüle
     *
     * Giriş yapmış kullanıcının profil bilgilerini döndürür.
     * Bu endpoint JWT middleware ile korunur.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        return response()->json([
            'success' => true,
            'message' => 'Profil Görüntüleme Başarılı!',
            'data' => JWTAuth::user()    // Token'dan kullanıcı bilgilerini al
        ]);
    }

    /**
     * Kullanıcı profil güncelleme işlemi
     *
     * Kullanıcının adını, email'ini ve şifresini güncelleme imkanı sağlar.
     * Şifre değişikliği için mevcut şifre zorunludur.
     *
     * @param Request $request - Güncellenecek bilgileri içeren HTTP isteği
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        // Giriş yapmış kullanıcıyı al
        $user = JWTAuth::user();

        // Güncelleme validasyon kuralları
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|min:2',                           // Opsiyonel ad güncellemesi
            'email' => 'sometimes|required|string|email|unique:users,email,' . $user->id,  // Mevcut kullanıcı hariç benzersiz email
            'current_password' => 'required_with:password|string',                 // Şifre değişikliğinde mevcut şifre zorunlu
            'password' => 'sometimes|required|string|confirmed|min:8'              // Yeni şifre ve onayı
        ]);

        // Validasyon hatası kontrolü
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasyon Hatası',
                'errors' => $validator->errors()
            ], 422);
        }

        // Şifre değişikliği istenmişse
        if ($request->has('password')) {
            // Mevcut şifreyi kontrol et
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mevcut şifre yanlış'
                ], 400);
            }
            // Yeni şifreyi hashleyerek kaydet
            $user->password = Hash::make($request->password);
        }

        // Ad güncellenmek isteniyorsa
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        // Email güncellenmek isteniyorsa
        if ($request->has('email')) {
            $user->email = $request->email;
        }

        // Değişiklikleri veritabanına kaydet
        $user->save();

        // Güncellenmiş kullanıcı bilgileri ile başarılı yanıt
        return response()->json([
            'success' => true,
            'message' => 'Profil başarıyla güncellendi',
            'data' => $user->fresh()    // Veritabanından güncel bilgileri al
        ]);
    }

    /**
     * Kullanıcı çıkış işlemi
     *
     * Kullanıcının JWT token'ını geçersiz kılar ve güvenli çıkış sağlar.
     * Token bir kez geçersiz kılındıktan sonra tekrar kullanılamaz.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // Mevcut token'ı geçersiz kıl
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Başarıyla Çıkış Yapıldı!'
            ]);
        } catch (JWTException $e) {
            // Token geçersiz kılma hatası
            return response()->json([
                'success' => false,
                'message' => 'Çıkış Yapılırken Bir Hata Oluştu'
            ], 500);
        }
    }
}
