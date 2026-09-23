import 'package:image_picker/image_picker.dart';

/// Sélection d'une photo (appareil photo), isolée derrière une interface pour que les tests
/// d'écran n'aient pas besoin d'un vrai sélecteur système — même logique que
/// `DepotDeSession`/`DepotDeSessionEnMemoire` dans `residences_api_client`.
abstract interface class SelecteurDePhoto {
  /// Chemin du fichier choisi, ou `null` si l'agent a annulé.
  Future<String?> choisir();
}

/// Appareil photo du téléphone (`image_picker`). La galerie n'est pas proposée : la fiche de
/// police et l'état des lieux attendent une preuve prise sur place, pas une photo existante.
final class SelecteurDePhotoSysteme implements SelecteurDePhoto {
  SelecteurDePhotoSysteme([ImagePicker? picker]) : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  @override
  Future<String?> choisir() async {
    final fichier = await _picker.pickImage(source: ImageSource.camera, imageQuality: 85);
    return fichier?.path;
  }
}
