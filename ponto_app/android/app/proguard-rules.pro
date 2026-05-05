# Flutter + Firebase + Riverpod - regras para evitar crash no release build

# Atributos usados por CameraX / reflexão / Kotlin
-keepattributes Signature,*Annotation*,InnerClasses,EnclosingMethod

# Flutter
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }
-dontwarn io.flutter.**

# Firebase
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-dontwarn com.google.firebase.**
-dontwarn com.google.android.gms.**

# Firebase Messaging
-keep class com.google.firebase.messaging.** { *; }
-keep class io.flutter.plugins.firebase.messaging.** { *; }

# Camera plugin (Flutter camera 0.10+ / 0.11+ com CameraX)
-keep class io.flutter.plugins.camera.** { *; }
-keep class io.flutter.plugins.camerax.** { *; }
-keepclassmembers class io.flutter.plugins.camera.** { *; }
-keepclassmembers class io.flutter.plugins.camerax.** { *; }
-dontwarn io.flutter.plugins.camera.**
-dontwarn io.flutter.plugins.camerax.**

# Guava — dependência explícita do camera_android_camerax (CameraX); o R8 remove demasiado e quebra o preview em release
-keep class com.google.common.** { *; }
-dontwarn com.google.common.**
-keep class com.google.thirdparty.publicsuffix.** { *; }

# CameraX — necessário para preview e captura de imagem
-keep class androidx.camera.** { *; }
-keep interface androidx.camera.** { *; }
-keepclassmembers class androidx.camera.** { *; }
-keepclassmembers interface androidx.camera.** { *; }
-dontwarn androidx.camera.**

# CameraX: evita remoção de classes usadas via reflexão
-keepclassmembers class * extends androidx.camera.core.UseCase {
    public <init>(...);
    public <init>();
}
-keepclassmembers class * implements androidx.camera.core.ImageAnalysis$Analyzer {
    public void analyze(androidx.camera.core.ImageProxy);
}

# Lifecycle (necessário para CameraX)
-keep class androidx.lifecycle.** { *; }
-keep interface androidx.lifecycle.** { *; }
-dontwarn androidx.lifecycle.**

# Concurrent / Executor (usado internamente pelo CameraX)
-keep class androidx.concurrent.** { *; }
-dontwarn androidx.concurrent.**

# Permission Handler (solicitar permissão de câmera em runtime)
-keep class com.baseflow.permissionhandler.** { *; }
-dontwarn com.baseflow.permissionhandler.**

# Geolocator
-keep class com.baseflow.geolocator.** { *; }
-dontwarn com.baseflow.geolocator.**

# Network Info Plus
-keep class dev.fluttercommunity.plus.network_info.** { *; }
-dontwarn dev.fluttercommunity.plus.network_info.**

# Local Auth
-keep class io.flutter.plugins.localauth.** { *; }
-dontwarn io.flutter.plugins.localauth.**

# SQLite / sqflite
-keep class com.tekartik.sqflite.** { *; }
-dontwarn com.tekartik.sqflite.**

# Shared Preferences
-keep class io.flutter.plugins.sharedpreferences.** { *; }

# URL Launcher
-keep class io.flutter.plugins.urllauncher.** { *; }

# Google Maps (Flutter)
-keep class com.google.android.gms.maps.** { *; }
-keep interface com.google.android.gms.maps.** { *; }
-dontwarn com.google.android.gms.maps.**
-keep class io.flutter.plugins.googlemaps.** { *; }

# Kotlin coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}
-dontwarn kotlinx.coroutines.**

# Evitar remoção de construtores usados por reflexão
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Manter enums
-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}
