<?php

namespace App\Enums;

enum DocumentType: string
{
    case RegistrationCertificate = 'registration_certificate';
    case BankProof = 'bank_proof';
    case PicLetter = 'pic_letter';
    case PremisesPhoto = 'premises_photo';
    case CampaignSupport = 'campaign_support';
    case CoverImage = 'cover_image';
    case GalleryImage = 'gallery_image';
    case UpdateImage = 'update_image';
    case ProofInvoice = 'proof_invoice';
    case ProofTransferSlip = 'proof_transfer_slip';
    case ProofExchangeStatement = 'proof_exchange_statement';
}
