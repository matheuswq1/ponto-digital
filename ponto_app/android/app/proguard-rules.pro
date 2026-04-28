# Flutter + Firebase + Riverpod - regras para evitar crash no release build

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

# Camera
-keep class io.flutter.plugins.camerax.** { *; }
-dontwarn io.flutter.plugins.camerax.**

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
