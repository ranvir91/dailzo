/// Opens/saves invoice PDF bytes for the user. Behavior is platform-specific
/// (native file + system viewer on Android/iOS/desktop; a browser download
/// on web — see the two implementations below), picked at compile time via
/// this conditional export so neither platform's unsupported APIs (dart:html
/// on native, dart:io-backed plugins on web) end up in the other's build.
library;

export 'invoice_downloader_io.dart'
    if (dart.library.html) 'invoice_downloader_web.dart';
