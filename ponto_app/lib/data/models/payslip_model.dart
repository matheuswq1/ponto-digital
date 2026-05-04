class PayslipModel {
  final int id;
  final int referenceMonth;
  final int referenceYear;
  final String referenceLabel;
  final String fileUrl;
  final String fileName;
  final int? fileSize;
  final String? fileSizeLabel;
  final String? description;
  final String? createdAt;

  const PayslipModel({
    required this.id,
    required this.referenceMonth,
    required this.referenceYear,
    required this.referenceLabel,
    required this.fileUrl,
    required this.fileName,
    this.fileSize,
    this.fileSizeLabel,
    this.description,
    this.createdAt,
  });

  factory PayslipModel.fromJson(Map<String, dynamic> json) => PayslipModel(
        id: json['id'],
        referenceMonth: json['reference_month'],
        referenceYear: json['reference_year'],
        referenceLabel: json['reference_label'] ?? '',
        fileUrl: json['file_url'] ?? '',
        fileName: json['file_name'] ?? '',
        fileSize: json['file_size'] as int?,
        fileSizeLabel: json['file_size_label'] as String?,
        description: json['description'] as String?,
        createdAt: json['created_at'] as String?,
      );

  String get displayTitle => description?.isNotEmpty == true ? description! : 'Holerite';
}
