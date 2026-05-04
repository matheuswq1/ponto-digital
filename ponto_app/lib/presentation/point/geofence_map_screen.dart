import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_theme.dart';
import '../../data/models/user_model.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Modelo de dado
// ─────────────────────────────────────────────────────────────────────────────

class GeofenceMapPlace {
  final String name;
  final String? address;
  final double latitude;
  final double longitude;
  final int radiusMeters;

  const GeofenceMapPlace({
    required this.name,
    this.address,
    required this.latitude,
    required this.longitude,
    required this.radiusMeters,
  });
}

/// Constrói lista de locais a partir do modelo de empresa.
List<GeofenceMapPlace> geofencePlacesFromCompany(CompanyModel? company) {
  final out = <GeofenceMapPlace>[];
  for (final loc in company?.geofences ?? <CompanyLocationModel>[]) {
    out.add(GeofenceMapPlace(
      name: loc.name,
      address: loc.address,
      latitude: loc.latitude,
      longitude: loc.longitude,
      radiusMeters: loc.radiusMeters,
    ));
  }
  final legacy = company?.geofence;
  if (out.isEmpty &&
      legacy != null &&
      legacy.enabled &&
      legacy.latitude != null &&
      legacy.longitude != null) {
    out.add(GeofenceMapPlace(
      name: company?.name ?? 'Empresa',
      address: null,
      latitude: legacy.latitude!,
      longitude: legacy.longitude!,
      radiusMeters: legacy.radiusMeters,
    ));
  }
  return out;
}

// ─────────────────────────────────────────────────────────────────────────────
// Paleta
// ─────────────────────────────────────────────────────────────────────────────

const _kPinColors = <Color>[
  Color(0xFF1565C0), // azul
  Color(0xFF2E7D32), // verde
  Color(0xFF6A1B9A), // roxo
  Color(0xFFEF6C00), // laranja
  Color(0xFFC62828), // vermelho
  Color(0xFF00838F), // teal
];

const _kMarkerHues = <double>[
  BitmapDescriptor.hueAzure,
  BitmapDescriptor.hueGreen,
  BitmapDescriptor.hueViolet,
  BitmapDescriptor.hueOrange,
  BitmapDescriptor.hueRose,
  BitmapDescriptor.hueCyan,
];

// ─────────────────────────────────────────────────────────────────────────────
// Tela principal
// ─────────────────────────────────────────────────────────────────────────────

class GeofenceMapScreen extends StatefulWidget {
  final String companyName;
  final List<GeofenceMapPlace> places;
  final double? userLatitude;
  final double? userLongitude;

  const GeofenceMapScreen({
    super.key,
    required this.companyName,
    required this.places,
    this.userLatitude,
    this.userLongitude,
  });

  @override
  State<GeofenceMapScreen> createState() => _GeofenceMapScreenState();
}

class _GeofenceMapScreenState extends State<GeofenceMapScreen> {
  GoogleMapController? _controller;
  int _selectedIndex = 0;

  // ── Geometria ─────────────────────────────────────────────────────────────

  LatLngBounds _bounds() {
    final pts = <LatLng>[];
    for (final p in widget.places) {
      final m = p.radiusMeters.toDouble();
      final dLat = m / 111320.0;
      final cosV = math.max(0.15, math.cos(p.latitude * math.pi / 180.0).abs());
      final dLng = m / (111320.0 * cosV);
      pts
        ..add(LatLng(p.latitude + dLat, p.longitude + dLng))
        ..add(LatLng(p.latitude - dLat, p.longitude - dLng));
    }
    final uLat = widget.userLatitude;
    final uLng = widget.userLongitude;
    if (uLat != null && uLng != null) pts.add(LatLng(uLat, uLng));

    if (pts.isEmpty) {
      return LatLngBounds(
        southwest: const LatLng(-23.56, -46.64),
        northeast: const LatLng(-23.54, -46.62),
      );
    }

    var minLat = pts.first.latitude;
    var maxLat = pts.first.latitude;
    var minLng = pts.first.longitude;
    var maxLng = pts.first.longitude;
    for (final p in pts) {
      if (p.latitude < minLat) minLat = p.latitude;
      if (p.latitude > maxLat) maxLat = p.latitude;
      if (p.longitude < minLng) minLng = p.longitude;
      if (p.longitude > maxLng) maxLng = p.longitude;
    }
    // padding mínimo
    if (maxLat - minLat < 1e-4) { minLat -= 0.002; maxLat += 0.002; }
    if (maxLng - minLng < 1e-4) { minLng -= 0.002; maxLng += 0.002; }
    return LatLngBounds(
      southwest: LatLng(minLat, minLng),
      northeast: LatLng(maxLat, maxLng),
    );
  }

  CameraPosition _initialCamera() {
    if (widget.places.isEmpty) {
      return const CameraPosition(target: LatLng(-23.55, -46.63), zoom: 12);
    }
    final b = _bounds();
    final center = LatLng(
      (b.southwest.latitude + b.northeast.latitude) / 2,
      (b.southwest.longitude + b.northeast.longitude) / 2,
    );
    return CameraPosition(target: center, zoom: 14);
  }

  // ── Elementos do mapa ─────────────────────────────────────────────────────

  Set<Circle> _circles() {
    final out = <Circle>{};
    for (var i = 0; i < widget.places.length; i++) {
      final p = widget.places[i];
      final col = _kPinColors[i % _kPinColors.length];
      final selected = i == _selectedIndex;
      out.add(Circle(
        circleId: CircleId('gf_$i'),
        center: LatLng(p.latitude, p.longitude),
        radius: p.radiusMeters.toDouble(),
        fillColor: col.withValues(alpha: selected ? 0.28 : 0.14),
        strokeColor: col.withValues(alpha: selected ? 1.0 : 0.65),
        strokeWidth: selected ? 3 : 2,
      ));
    }
    return out;
  }

  Set<Marker> _markers() {
    final out = <Marker>{};
    for (var i = 0; i < widget.places.length; i++) {
      final p = widget.places[i];
      out.add(Marker(
        markerId: MarkerId('loc_$i'),
        position: LatLng(p.latitude, p.longitude),
        icon: BitmapDescriptor.defaultMarkerWithHue(
            _kMarkerHues[i % _kMarkerHues.length]),
        infoWindow: InfoWindow(
          title: '${i + 1}. ${p.name}',
          snippet: p.address ?? 'Raio: ${p.radiusMeters} m',
        ),
        onTap: () => _selectPlace(i),
      ));
    }
    final uLat = widget.userLatitude;
    final uLng = widget.userLongitude;
    if (uLat != null && uLng != null) {
      out.add(Marker(
        markerId: const MarkerId('user'),
        position: LatLng(uLat, uLng),
        icon: BitmapDescriptor.defaultMarkerWithHue(210), // azul vivo
        infoWindow: const InfoWindow(title: 'Você está aqui'),
      ));
    }
    return out;
  }

  // ── Eventos ───────────────────────────────────────────────────────────────

  void _onMapCreated(GoogleMapController controller) {
    _controller = controller;
    // Aguarda o frame seguinte para o mapa estar renderizado antes de mover a câmera
    WidgetsBinding.instance.addPostFrameCallback((_) => _fitBounds());
  }

  Future<void> _fitBounds() async {
    final c = _controller;
    if (c == null || widget.places.isEmpty) return;
    try {
      await c.animateCamera(CameraUpdate.newLatLngBounds(_bounds(), 64));
    } catch (_) {}
  }

  void _selectPlace(int i) {
    setState(() => _selectedIndex = i);
    final p = widget.places[i];
    _controller?.animateCamera(
      CameraUpdate.newLatLng(LatLng(p.latitude, p.longitude)),
    );
  }

  Future<void> _openDirections(GeofenceMapPlace p) async {
    final uLat = widget.userLatitude;
    final uLng = widget.userLongitude;
    final origin = (uLat != null && uLng != null) ? '&origin=$uLat,$uLng' : '';
    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1$origin&destination=${p.latitude},${p.longitude}',
    );
    if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  // ── UI ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final hasUser = widget.userLatitude != null && widget.userLongitude != null;

    return Scaffold(
      backgroundColor: Colors.grey.shade100,
      appBar: AppBar(
        elevation: 0,
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        centerTitle: false,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios, size: 20),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Áreas autorizadas',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            Text(
              widget.companyName,
              style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Enquadrar tudo',
            icon: const Icon(Icons.fit_screen_outlined, size: 22),
            onPressed: _fitBounds,
          ),
        ],
      ),
      body: widget.places.isEmpty
          ? _buildEmpty()
          : Stack(
              children: [
                // ── Mapa ──────────────────────────────────────────────────
                GoogleMap(
                  initialCameraPosition: _initialCamera(),
                  onMapCreated: _onMapCreated,
                  circles: _circles(),
                  markers: _markers(),
                  mapType: MapType.normal,
                  compassEnabled: true,
                  myLocationButtonEnabled: false,
                  mapToolbarEnabled: false,
                  zoomControlsEnabled: false,
                  buildingsEnabled: true,
                  rotateGesturesEnabled: true,
                  scrollGesturesEnabled: true,
                  tiltGesturesEnabled: false,
                  zoomGesturesEnabled: true,
                ),

                // ── Legenda (canto superior direito) ──────────────────────
                if (hasUser)
                  Positioned(
                    top: 12,
                    right: 12,
                    child: _LegendBadge(hasUser: hasUser),
                  ),

                // ── Botões de zoom (direita, verticais) ───────────────────
                Positioned(
                  right: 12,
                  bottom: _cardHeight + 20,
                  child: Column(
                    children: [
                      _ZoomButton(
                        icon: Icons.add,
                        onTap: () => _controller?.animateCamera(CameraUpdate.zoomIn()),
                      ),
                      const SizedBox(height: 6),
                      _ZoomButton(
                        icon: Icons.remove,
                        onTap: () => _controller?.animateCamera(CameraUpdate.zoomOut()),
                      ),
                    ],
                  ),
                ),

                // ── Painel inferior ────────────────────────────────────────
                Align(
                  alignment: Alignment.bottomCenter,
                  child: _BottomPanel(
                    places: widget.places,
                    selectedIndex: _selectedIndex,
                    onSelect: _selectPlace,
                    onDirections: _openDirections,
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildEmpty() => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.location_off_outlined, size: 64, color: Colors.grey.shade400),
            const SizedBox(height: 16),
            Text(
              'Nenhuma área configurada',
              style: TextStyle(color: Colors.grey.shade600, fontSize: 16),
            ),
          ],
        ),
      );

  // altura aproximada do painel inferior para posicionar os botões de zoom
  static const double _cardHeight = 178;
}

// ─────────────────────────────────────────────────────────────────────────────
// Widgets auxiliares
// ─────────────────────────────────────────────────────────────────────────────

class _LegendBadge extends StatelessWidget {
  final bool hasUser;
  const _LegendBadge({required this.hasUser});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: 6)],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          _legendItem(color: Colors.transparent, borderColor: AppColors.primary, label: 'Área autorizada'),
          if (hasUser) ...[
            const SizedBox(height: 4),
            _legendItem(color: const Color(0xFF2979FF), borderColor: const Color(0xFF2979FF), label: 'Sua posição'),
          ],
        ],
      ),
    );
  }

  Widget _legendItem({required Color color, required Color borderColor, required String label}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 12, height: 12,
          decoration: BoxDecoration(
            color: color == Colors.transparent ? color : color.withValues(alpha: 0.25),
            shape: BoxShape.circle,
            border: Border.all(color: borderColor, width: 2),
          ),
        ),
        const SizedBox(width: 6),
        Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textSecondary)),
      ],
    );
  }
}

class _ZoomButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _ZoomButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 38, height: 38,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 6)],
        ),
        child: Icon(icon, size: 20, color: AppColors.textPrimary),
      ),
    );
  }
}

class _BottomPanel extends StatelessWidget {
  final List<GeofenceMapPlace> places;
  final int selectedIndex;
  final void Function(int) onSelect;
  final void Function(GeofenceMapPlace) onDirections;

  const _BottomPanel({
    required this.places,
    required this.selectedIndex,
    required this.onSelect,
    required this.onDirections,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: [
          BoxShadow(color: Color(0x22000000), blurRadius: 16, offset: Offset(0, -4)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Puxador
          Container(
            margin: const EdgeInsets.only(top: 10, bottom: 4),
            width: 36, height: 4,
            decoration: BoxDecoration(
              color: Colors.grey.shade300,
              borderRadius: BorderRadius.circular(2),
            ),
          ),

          // Label
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: Row(
              children: [
                const Icon(Icons.place, size: 16, color: AppColors.primary),
                const SizedBox(width: 6),
                Text(
                  '${places.length} local${places.length > 1 ? 'is' : ''}',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          ),

          // Carrossel de cards
          SizedBox(
            height: 110,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 0),
              itemCount: places.length,
              itemBuilder: (context, i) {
                final p = places[i];
                final col = _kPinColors[i % _kPinColors.length];
                final selected = i == selectedIndex;
                return _PlaceCard(
                  place: p,
                  index: i,
                  color: col,
                  selected: selected,
                  onTap: () => onSelect(i),
                  onDirections: () => onDirections(p),
                );
              },
            ),
          ),

          SizedBox(height: MediaQuery.of(context).padding.bottom + 10),
        ],
      ),
    );
  }
}

class _PlaceCard extends StatelessWidget {
  final GeofenceMapPlace place;
  final int index;
  final Color color;
  final bool selected;
  final VoidCallback onTap;
  final VoidCallback onDirections;

  const _PlaceCard({
    required this.place,
    required this.index,
    required this.color,
    required this.selected,
    required this.onTap,
    required this.onDirections,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        width: 210,
        margin: const EdgeInsets.fromLTRB(4, 0, 4, 8),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.07) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? color : Colors.grey.shade200,
            width: selected ? 2 : 1,
          ),
          boxShadow: selected
              ? [BoxShadow(color: color.withValues(alpha: 0.18), blurRadius: 10, offset: const Offset(0, 3))]
              : [const BoxShadow(color: Color(0x0A000000), blurRadius: 4)],
        ),
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                // Número do local
                Container(
                  width: 26, height: 26,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '${index + 1}',
                    style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    place.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                      color: selected ? color : AppColors.textPrimary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 5),
            if (place.address != null) ...[
              Text(
                place.address!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 3),
            ],
            Row(
              children: [
                Icon(Icons.radio_button_checked, size: 12, color: color.withValues(alpha: 0.8)),
                const SizedBox(width: 4),
                Text(
                  'Raio: ${place.radiusMeters} m',
                  style: TextStyle(fontSize: 11, color: color.withValues(alpha: 0.9)),
                ),
                const Spacer(),
                // botão rotas
                GestureDetector(
                  onTap: onDirections,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.directions, size: 13, color: color),
                        const SizedBox(width: 3),
                        Text('Rotas', style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
