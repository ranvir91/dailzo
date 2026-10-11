import 'dart:typed_data';
// This file is only ever compiled into the web build (see the conditional
// export in invoice_downloader.dart), so dart:html is safe here.
// ignore: avoid_web_libraries_in_flutter, deprecated_member_use
import 'dart:html' as html;

/// Browsers have no filesystem to write to (that's why path_provider's
/// getTemporaryDirectory() throws MissingPluginException on web) — a PDF is
/// instead handed to the browser directly as a Blob and opened in a new tab,
/// which the browser renders inline or downloads per its own PDF settings.
Future<String?> openInvoiceBytes(Uint8List bytes, String filename) async {
  final blob = html.Blob([bytes], 'application/pdf');
  final url = html.Url.createObjectUrlFromBlob(blob);
  html.window.open(url, '_blank');
  // Revoking immediately can race the new tab's fetch of the blob URL, so
  // this is delayed well past that instead of never revoking at all.
  Future.delayed(const Duration(minutes: 1), () => html.Url.revokeObjectUrl(url));
  return null;
}
