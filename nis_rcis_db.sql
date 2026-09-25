-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 21, 2026 at 12:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nis_rcis_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `forenames` varchar(100) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `passport_number` varchar(50) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `draft_data` longtext DEFAULT NULL,
  `draft_step` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `email`, `password_hash`, `surname`, `forenames`, `nationality`, `passport_number`, `phone`, `photo_path`, `draft_data`, `draft_step`, `created_at`, `updated_at`, `email_verified`, `verification_token`) VALUES
(4, 'ezeab.nis@gmail.com', '$2y$10$4N3UtHr2LOQBGiqt0eCaV.Vx3Xam2a2S8bVYYsGcoXXfmtMes0VJ2', 'EZE', 'KOBI', 'UNITED STATES', 'B6231526', '+2348067707218', 'uploads/photos/test.jpg', NULL, 1, '2026-09-15 18:36:41', '2026-09-17 10:20:17', 0, NULL),
(7, 'mmaluv22@gmail.com', '$2y$10$Jvru9LNH0f/56QyeQKNOpe9HunBbjpMSIGDHTuY1HWt11LlwJ5izO', 'STEPHEN', 'ANENE', '', '', '+2348067707218', NULL, '{\"csrf_token\":\"5886c044e255a299b8539950e08df5658d0103740938a9b2fbf3a1dea45323e9\",\"action_submit_application\":\"1\",\"action_save_and_exit\":\"1\",\"current_step_saved\":5,\"existing_photo_path\":\"uploads\\/photos\\/photo_draft_7_1789505050.jpeg\",\"existing_doc_passport_copy\":\"uploads\\/documents\\/doc_passport_copy_draft_7_1789505950.jpeg\",\"existing_doc_residence_visa\":\"uploads\\/documents\\/doc_residence_visa_draft_7_1789505950.jpeg\",\"existing_doc_quota_approval\":\"uploads\\/documents\\/doc_quota_approval_draft_7_1789505950.jpeg\",\"existing_doc_domicile_proof\":\"\",\"existing_doc_additional\":\"\",\"passport_number\":\"knjkhjhjh\",\"passport_issue_date\":\"2026-09-15\",\"passport_expiry\":\"2029-09-17\",\"surname\":\"STEPHEN\",\"forenames\":\"ANENE\",\"nationality\":\"BARBADOS\",\"sex\":\"MALE\",\"date_of_birth\":\"2022-02-11\",\"place_of_birth\":\"Us\",\"profession\":\"civil servant\",\"height\":\"180\",\"blood_group\":\"O+\",\"complexion\":\"DARK\",\"eye_color\":\"BLACK\",\"hair_color\":\"BLONDE\",\"distinguished_features\":\"\",\"email\":\"mmaluv22@gmail.com\",\"phone\":\"+2348067707218\",\"residential_address\":\"Plot 812 Durumi\",\"residential_state\":\"Kaduna\",\"residential_lga\":\"Makarfi\",\"residential_city\":\"Iwollo Oghe\",\"change_address\":\"\",\"change_state\":\"\",\"change_lga\":\"\",\"change_city\":\"\",\"change_of_address\":\"\",\"domicile\":\"Plot 812 Durumi, Iwollo Oghe, Makarfi LGA, Kaduna STATE\",\"emergency_contact_name\":\"Adamma Eze\",\"emergency_contact_relation\":\"GUARDIAN\",\"emergency_contact_phone\":\"08104042971\",\"emergency_contact_email\":\"mmaluv22@gmail.com\",\"kin_address\":\"Plot 812 Durumi\",\"kin_state\":\"Jigawa\",\"kin_lga\":\"Kiyawa\",\"kin_city\":\"Iwollo Oghe\",\"emergency_contact_address\":\"Plot 812 Durumi, Iwollo Oghe, Kiyawa LGA, Jigawa STATE\",\"docs_genuine_confirm\":\"1\",\"review_acknowledgement_check\":\"1\",\"payment_method\":\"PAYSTACK\",\"payment_status\":\"PENDING\",\"payment_reference\":\"\",\"enrollment_center\":\"NIS HQ\",\"appointment_date\":\"2026-09-18\",\"appointment_time\":\"09:00 AM\",\"saved_at\":\"15 Sep 2026, 10:59 PM\"}', 5, '2026-09-15 20:37:28', '2026-09-15 20:59:10', 1, NULL),
(10, 'notif_test_1789508324@example.com', 'hash', 'TestUser', 'OfficerNotice', 'United Kingdom', 'GB99887766', '', NULL, NULL, 1, '2026-09-15 21:38:44', '2026-09-15 21:38:44', 0, NULL),
(12, 'notif_pending_1789508341@example.com', 'hash', 'PendingUser', 'AwaitingNotice', 'France', 'FR11223344', '', NULL, NULL, 1, '2026-09-15 21:39:01', '2026-09-15 21:39:01', 0, NULL),
(17, 'adamma@gmail.com', '$2y$10$/cw8hHTlryvX5VHX/oknE.jF.H.oDpf7BzsNh8V0IwhybQz79HoVK', 'EZE', 'ADAMMA', '', '', '+2348067707218', NULL, '{\"csrf_token\":\"423909742ab87c088e31df9cf011f7f0cf4ae7ada4bc8e9faa35e5b55c9b9689\",\"action_submit_application\":\"1\",\"action_save_and_exit\":\"1\",\"current_step_saved\":5,\"existing_photo_path\":\"uploads\\/photos\\/photo_draft_17_1789731884.png\",\"existing_doc_passport_copy\":\"uploads\\/documents\\/doc_passport_copy_draft_17_1789731884.png\",\"existing_doc_residence_visa\":\"uploads\\/documents\\/doc_residence_visa_draft_17_1789731884.png\",\"existing_doc_quota_approval\":\"uploads\\/documents\\/doc_quota_approval_draft_17_1789731884.png\",\"existing_doc_domicile_proof\":\"\",\"existing_doc_additional\":\"\",\"passport_number\":\"B3455557\",\"passport_issue_date\":\"2026-09-18\",\"passport_expiry\":\"2029-09-18\",\"surname\":\"EZE\",\"forenames\":\"ADAMMA\",\"nationality\":\"AUSTRIA\",\"sex\":\"FEMALE\",\"date_of_birth\":\"2019-01-25\",\"place_of_birth\":\"us\",\"profession\":\"nurse\",\"height\":\"167\",\"blood_group\":\"B-\",\"complexion\":\"DARK\",\"eye_color\":\"BLACK\",\"hair_color\":\"BLACK\",\"distinguished_features\":\"\",\"email\":\"adamma@gmail.com\",\"phone\":\"+2348067707218\",\"residential_address\":\"Nigeria Immigration Service Headquarters, Sauka Airport Road\",\"residential_state\":\"Federal Capital Territory (FCT)\",\"residential_lga\":\"Bwari\",\"residential_city\":\"Abuja\",\"change_address\":\"\",\"change_state\":\"\",\"change_lga\":\"\",\"change_city\":\"\",\"change_of_address\":\"\",\"domicile\":\"Nigeria Immigration Service Headquarters, Sauka Airport Road, Abuja, Bwari LGA, Federal Capital Territory (FCT) STATE\",\"emergency_contact_name\":\"Adamma Blessing Eze\",\"emergency_contact_relation\":\"NIECE\",\"emergency_contact_phone\":\"08067707218\",\"emergency_contact_email\":\"mmaluv22@gmail.com\",\"kin_address\":\"Plot 1052 Nifa Estate, Reuben Okoya Crescent Wuye\",\"kin_state\":\"Federal Capital Territory (FCT)\",\"kin_lga\":\"Abaji\",\"kin_city\":\"Abuja\",\"emergency_contact_address\":\"Plot 1052 Nifa Estate, Reuben Okoya Crescent Wuye, Abuja, Abaji LGA, Federal Capital Territory (FCT) STATE\",\"docs_genuine_confirm\":\"1\",\"payment_method\":\"PAYSTACK\",\"payment_status\":\"PENDING\",\"payment_reference\":\"\",\"enrollment_center\":\"NIS HQ\",\"appointment_date\":\"2026-09-21\",\"appointment_time\":\"09:00 AM\",\"saved_at\":\"18 Sep 2026, 01:44 PM\"}', 5, '2026-09-17 09:01:42', '2026-09-18 11:44:44', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `application_number` varchar(50) NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `forenames` varchar(100) NOT NULL,
  `nationality` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `place_of_birth` varchar(100) NOT NULL,
  `sex` varchar(20) NOT NULL,
  `height` varchar(30) DEFAULT NULL,
  `complexion` varchar(50) DEFAULT NULL,
  `eye_color` varchar(50) DEFAULT NULL,
  `hair_color` varchar(50) DEFAULT NULL,
  `distinguished_features` varchar(150) DEFAULT NULL,
  `profession` varchar(100) NOT NULL,
  `domicile` text NOT NULL,
  `passport_number` varchar(50) NOT NULL,
  `passport_issue_date` date DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `national_id_number` varchar(50) DEFAULT NULL,
  `tax_id_number` varchar(50) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_relation` varchar(50) NOT NULL,
  `emergency_contact_phone` varchar(50) NOT NULL,
  `emergency_contact_address` text NOT NULL,
  `blood_group` varchar(20) DEFAULT 'UNKNOWN',
  `enrollment_center` varchar(100) NOT NULL DEFAULT 'Abuja Central Enrollment Center',
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(20) NOT NULL DEFAULT '09:00 AM',
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 35000.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'PAID',
  `payment_date` datetime DEFAULT NULL,
  `payment_reference` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'PENDING_APPROVAL',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `fingerprint_data` text DEFAULT NULL,
  `biometrics_captured_by` int(11) DEFAULT NULL,
  `biometrics_captured_at` datetime DEFAULT NULL,
  `card_id` int(11) DEFAULT NULL,
  `card_ready_notified` tinyint(1) DEFAULT 0,
  `card_ready_notified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `doc_passport_copy` varchar(255) DEFAULT NULL,
  `doc_residence_visa` varchar(255) DEFAULT NULL,
  `doc_quota_approval` varchar(255) DEFAULT NULL,
  `doc_domicile_proof` varchar(255) DEFAULT NULL,
  `doc_additional` varchar(255) DEFAULT NULL,
  `change_of_address` text DEFAULT NULL,
  `applicant_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `application_number`, `reference_number`, `surname`, `forenames`, `nationality`, `date_of_birth`, `place_of_birth`, `sex`, `height`, `complexion`, `eye_color`, `hair_color`, `distinguished_features`, `profession`, `domicile`, `passport_number`, `passport_issue_date`, `passport_expiry`, `national_id_number`, `tax_id_number`, `phone`, `email`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `emergency_contact_address`, `blood_group`, `enrollment_center`, `appointment_date`, `appointment_time`, `fee_amount`, `payment_status`, `payment_date`, `payment_reference`, `payment_method`, `status`, `approved_by`, `approved_at`, `approval_notes`, `photo_path`, `signature_path`, `fingerprint_data`, `biometrics_captured_by`, `biometrics_captured_at`, `card_id`, `card_ready_notified`, `card_ready_notified_at`, `created_at`, `updated_at`, `doc_passport_copy`, `doc_residence_visa`, `doc_quota_approval`, `doc_domicile_proof`, `doc_additional`, `change_of_address`, `applicant_id`) VALUES
(2, 'RC-2026-608549', 'APP-047C65ACCA', 'KOUASSI', 'JEAN BAPTISTE', 'CIV', '1988-05-14', 'Abidjan', 'MALE', '182', 'DARK', 'BROWN', 'BLACK', NULL, 'Software Architect', 'Plot 412, Constitution Ave, Central Area, Abuja', 'A41318807', '2023-01-10', '2027-04-10', NULL, NULL, '+2348012345678', 'jean.kouassi@example.com', 'Amara Kouassi', 'SPOUSE', '+2348098765432', 'Plot 412, Constitution Ave, Abuja', 'UNKNOWN', 'NIS HQ', '2026-09-25', '09:00 AM', 35000.00, 'PAID', '2026-09-10 11:45:27', 'CARD-20260910-44928-1122', 'CARD', 'APPROVED_FOR_BIOMETRICS', 1, '2026-09-10 11:45:27', NULL, 'uploads/photos/photo_sample.jpg', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-10 10:45:27', '2026-09-14 07:22:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'RC-2026-895242', 'APP-0CF38DCF18', 'EZE', 'ADAMMA', 'ALBANIA', '2021-07-11', 'YUUY', 'MALE', 'UU', 'DARK', 'BLACK', NULL, NULL, 'UUU', 'UTYU', '4TTYTU', '2026-09-10', '2028-10-10', NULL, NULL, '08104042971', 'blessingeze00034@gmail.com', 'ADAMMA BLESSING EZE', 'CHILD', '08067707218', 'PLOT 1052 NIFA ESTATE, REUBEN OKOYA CRESCENT WUY', 'AB-', 'NIS HQ', '2026-09-18', '09:00 AM', 35000.00, 'PAID', '2026-09-11 00:18:08', 'CARD-20260911-51433-7654', 'CARD', 'PENDING_APPROVAL', NULL, NULL, NULL, 'uploads/photos/photo_20260911_001808_1257bde6a861.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-10 22:18:08', '2026-09-14 07:22:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'RC-2026-433764', 'APP-24A1573581', 'EZE', 'ADAMMA', 'BARBADOS', '2025-09-01', 'TRY', 'FEMALE', '45', 'FAIR', 'BROWN', 'DARK BROWN', '545fgg', 'TYYY', 'RRTTYR', 'FGTR4554', '2026-09-14', '2029-08-14', NULL, NULL, '08096412228', 'blessingeze00034@gmail.com', 'ADAMMA EZE', 'GUARDIAN', '08104042971', '54TRRTDFFG', 'AB-', 'NIS HQ', '2026-09-17', '09:00 AM', 35000.00, 'PAID', '2026-09-14 11:06:06', 'TRF-20260914-25344', 'BANK_TRANSFER', 'PENDING_APPROVAL', NULL, NULL, NULL, 'uploads/photos/photo_20260914_110606_cd98f14c99c6.jpeg', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-14 09:06:06', '2026-09-14 09:06:06', 'uploads/documents/doc_pass_20260914_110606_accebd7f67d9.pdf', 'uploads/documents/doc_visa_20260914_110606_4fdb73098ae5.pdf', 'uploads/documents/doc_quota_20260914_110606_45ba2a7b39e3.pdf', 'uploads/documents/doc_domicile_20260914_110606_9acbea495243.pdf', NULL, '45TR', NULL),
(10, 'RC-2026-794981', 'APP-4F81078EF4', 'EZE', 'ADAMMA BLESSING', 'BAHAMAS', '2022-05-15', 'JOS', 'FEMALE', '45', 'DARK', 'GREY', 'GREY', NULL, 'BJHHJKJ', 'GHTGU', 'BHKUY76887', '2026-09-15', '2027-06-15', NULL, NULL, '08067707218', 'ezeab.nis@gmail.com', 'ADAMMA BLESSING EZE', 'NIECE', '08067707218', 'UIIUIKKJI', 'O+', 'NIS HQ', '2026-09-18', '09:00 AM', 35000.00, 'PAID', '2026-09-15 12:03:56', 'CARD-20260915-72130-4467', 'CARD', 'PENDING_APPROVAL', NULL, NULL, NULL, 'uploads/photos/photo_20260915_120356_76e72698.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-15 10:03:56', '2026-09-15 18:36:41', 'uploads/documents/doc_pass_20260915_120356_f5d9e489d179.png', 'uploads/documents/doc_visa_20260915_120356_6934d4a098f7.png', 'uploads/documents/doc_quota_20260915_120356_6920e6967d68.png', NULL, NULL, NULL, 4),
(12, 'RC-2026-577132', 'APP-A93BD0C10E', 'EZE', 'ADAMMA', 'RUSSIA', '2023-01-23', 'JOS', 'FEMALE', '178', 'FAIR', 'BROWN', 'BALD', 'scar', 'PUBLIC SERVANT', 'GWAGWALADA ABUJA', 'NBHBJHJUI6', '2026-09-15', '2027-10-15', NULL, NULL, '08096412228', 'blessingeze00034@gmail.com', 'ADAMMA BLESSING EZE', 'COUSIN', '08096412228', 'GWAGWALADA ABUJA', 'B-', 'NIS HQ', '2026-09-23', '09:00 AM', 35000.00, 'PAID', '2026-09-15 15:31:10', 'CARD-20260915-24860-1969', 'CARD', 'PENDING_APPROVAL', NULL, NULL, NULL, 'uploads/photos/photo_20260915_153110_20679e3046a7.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-15 13:31:10', '2026-09-15 13:31:10', 'uploads/documents/doc_pass_20260915_153110_e8fd1680ba96.png', 'uploads/documents/doc_visa_20260915_153110_0bb9ad12201d.png', 'uploads/documents/doc_quota_20260915_153110_9735c65808ee.png', NULL, NULL, NULL, NULL),
(20, 'NIS-APP-TEST-APR-260', '', 'TestUser', 'OfficerNotice', 'United Kingdom', '0000-00-00', '', '', NULL, NULL, NULL, NULL, NULL, '', '', 'GB99887766', NULL, NULL, NULL, NULL, '', '', '', '', '', '', 'UNKNOWN', 'NIS Headquarters, Abuja', '2026-10-01', '10:00 AM', 35000.00, 'PAID', NULL, NULL, NULL, 'APPROVED_FOR_BIOMETRICS', NULL, '2026-09-15 22:38:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-15 21:38:44', '2026-09-15 21:38:44', NULL, NULL, NULL, NULL, NULL, NULL, 10),
(24, 'NIS-APP-PEND-258', 'REF-PND-26624', 'PendingUser', 'AwaitingNotice', 'France', '0000-00-00', '', '', NULL, NULL, NULL, NULL, NULL, '', '', 'FR11223344', NULL, NULL, NULL, NULL, '', '', '', '', '', '', 'UNKNOWN', 'NIS Lagos State Command, Alagbon', '2026-10-15', '11:00 AM', 35000.00, 'PAID', NULL, NULL, NULL, 'PENDING_APPROVAL', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-15 21:39:01', '2026-09-15 21:39:01', NULL, NULL, NULL, NULL, NULL, NULL, 12),
(30, 'RC-2026-221974', 'APP-386BDB04E7', 'EZE', 'KOBI', 'UNITED STATES', '2021-01-15', 'US', 'FEMALE', '90-\'', 'DARK', NULL, NULL, NULL, 'LKKLKL-', 'NIGERIA IMMIGRATION SERVICE HEADQUARTERS, SAUKA AIRPORT ROAD, ABUJA, KIRI KASAMA LGA, JIGAWA STATE', 'B6231526G', '2026-09-15', '2029-09-15', NULL, NULL, '+2348067707218', 'ezeab.nis@gmail.com', 'ADAMMA BLESSING EZE', 'GUARDIAN', '08067707218', 'PLOT 1052 NIFA ESTATE, REUBEN OKOYA CRESCENT WUY, ABUJA, KUJE LGA, FEDERAL CAPITAL TERRITORY (FCT) STATE', 'AB+', 'NIS HQ', '2026-09-18', '09:00 AM', 35000.00, 'PAID', '2026-09-15 23:57:44', 'PSTK_1789508907528_ZFEM1K', 'PAYSTACK', 'PENDING_APPROVAL', NULL, NULL, NULL, 'uploads/photos/photo_20260915_235744_07547d5d05ea.jpeg', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-15 21:57:44', '2026-09-15 21:57:44', 'uploads/documents/doc_pass_20260915_235744_edf7abd826c5.png', 'uploads/documents/doc_visa_20260915_235744_8381fd1dd96e.png', 'uploads/documents/doc_quota_20260915_235744_1aa4d2448c1a.png', NULL, NULL, NULL, 4),
(31, 'RC-2026-977096', 'APP-8E8890E902', 'EZE', 'KOBI', 'UNITED STATES', '2022-06-16', 'US', 'FEMALE', '178', 'DARK', 'GREY', 'BALD', NULL, 'ENGINEER', 'NIGERIA IMMIGRATION SERVICE HEADQUARTERS, SAUKA AIRPORT ROAD, ABUJA, MUNICIPAL AREA COUNCIL (AMAC) LGA, FEDERAL CAPITAL TERRITORY (FCT) STATE', 'B6231526H', '2026-09-16', '2028-09-16', NULL, NULL, '+2348067707218', 'ezeab.nis@gmail.com', 'ADAMMA EZE', 'COUSIN', '08104042971', 'PLOT 812 DURUMI, IWOLLO OGHE, KIRI KASAMA LGA, JIGAWA STATE', 'B-', 'NIS HQ', '2026-09-19', '09:00 AM', 35000.00, 'PAID', '2026-09-16 08:36:20', 'PSTK_1789540499652_KZ0YYE', 'PAYSTACK', 'QUERIED', 1, '2026-09-17 10:10:53', 'ghghjhjh', 'uploads/photos/photo_draft_4_1789539646.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-16 06:36:20', '2026-09-17 09:10:53', 'uploads/documents/doc_pass_20260916_083620_910381968508.png', 'uploads/documents/doc_visa_20260916_083620_b6d1c9fb3779.png', 'uploads/documents/doc_quota_20260916_083620_34e77487bf73.png', NULL, NULL, NULL, 4),
(32, 'RC-2026-376645', 'APP-33C4FF4A16', 'EZE', 'KOBI', 'UNITED STATES', '2025-09-17', 'US', 'FEMALE', '178', 'FAIR', 'BLACK', 'DARK BROWN', NULL, 'ENGINEER', '5 AMBASSADOR UCHE OKEKE STREET,UNITY ESTATE KARU, NUMAN, GEZAWA LGA, KANO STATE', 'B6231526', '2023-07-17', '2028-09-17', NULL, NULL, '+2348067707218', 'ezeab.nis@gmail.com', 'ADAMMA BLESSING EZE', 'SISTER', '08067707218', 'PLOT 1052 NIFA ESTATE, REUBEN OKOYA CRESCENT WUY, ABUJA, KWALI LGA, FEDERAL CAPITAL TERRITORY (FCT) STATE', 'AB+', 'NIS HQ', '2026-09-20', '09:00 AM', 35000.00, 'PAID', '2026-09-17 12:20:17', 'PSTK_TRF_1789640256368_YGU1H9', 'PAYSTACK', 'APPROVED_FOR_BIOMETRICS', 1, '2026-09-17 12:02:54', 'Approved for physical biometrics capturing on appointment date.', 'uploads/photos/photo_draft_4_1789639160.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-17 10:20:17', '2026-09-17 11:02:54', 'uploads/documents/doc_passport_copy_draft_4_1789639160.png', 'uploads/documents/doc_residence_visa_draft_4_1789639160.png', 'uploads/documents/doc_quota_approval_draft_4_1789639160.png', NULL, NULL, NULL, 4),
(33, 'RC-2026-156721', 'APP-4F17DAE442', 'EZE', 'KOBI', 'UNITED STATES', '2021-01-04', 'US', 'MALE', '178', 'DARK', 'GREY', 'BALD', NULL, 'CHEF', 'PLOT 1052 NIFA ESTATE, REUBEN OKOYA CRESCENT WUY, ABUJA, KWALI LGA, FEDERAL CAPITAL TERRITORY (FCT) STATE', 'B6231526', '2026-09-17', '2028-09-17', NULL, NULL, '+2348067707218', 'ezeab.nis@gmail.com', 'ADAMMA EZE', 'NIECE', '08104042971', 'PLOT 812 DURUMI, IWOLLO OGHE, KAFIN HAUSA LGA, JIGAWA STATE', 'B-', 'NIS HQ', '2026-09-20', '09:00 AM', 35000.00, 'PAID', '2026-09-17 13:39:44', 'PSTK_1789644935860_HYOTAA', 'PAYSTACK', 'APPROVED_FOR_BIOMETRICS', 1, '2026-09-18 13:34:27', 'Approved for physical biometrics capturing on appointment date.', 'uploads/photos/photo_20260917_133944_02571ca0fb38.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-17 11:39:44', '2026-09-18 12:34:27', 'uploads/documents/doc_pass_20260917_133944_f8b7772a8176.png', 'uploads/documents/doc_visa_20260917_133944_c1a38840ae6e.png', 'uploads/documents/doc_quota_20260917_133944_66528792b95e.png', NULL, NULL, NULL, 4),
(34, 'RC-2026-597358', 'APP-606E3BFFBA', 'EZE', 'KOBI', 'UNITED STATES', '2021-01-04', 'US', 'MALE', '178', 'DARK', 'GREY', 'BALD', NULL, 'CHEF', 'PLOT 1052 NIFA ESTATE, REUBEN OKOYA CRESCENT WUY, ABUJA, KWALI LGA, FEDERAL CAPITAL TERRITORY (FCT) STATE', 'B6231526', '2026-09-17', '2028-09-17', NULL, NULL, '+2348067707218', 'ezeab.nis@gmail.com', 'ADAMMA EZE', 'NIECE', '08104042971', 'PLOT 812 DURUMI, IWOLLO OGHE, KAFIN HAUSA LGA, JIGAWA STATE', 'B-', 'NIS HQ', '2026-09-20', '09:00 AM', 35000.00, 'PAID', '2026-09-17 13:49:03', 'PSTK_1789644935860_HYOTAA', 'PAYSTACK', 'QUERIED', 1, '2026-09-18 13:34:14', 'gfnbjjkjuk', 'uploads/photos/photo_20260917_134903_a679451ab484.png', NULL, NULL, NULL, NULL, NULL, 0, NULL, '2026-09-17 11:49:03', '2026-09-18 12:34:14', 'uploads/documents/doc_pass_20260917_134903_262f3a83b821.png', 'uploads/documents/doc_visa_20260917_134903_000c08b284cc.png', 'uploads/documents/doc_quota_20260917_134903_b80d75a952ac.png', NULL, NULL, NULL, 4);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `card_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `card_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin', 'LOGIN_SUCCESS', NULL, 'User admin logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:06:58'),
(2, 1, 'admin', 'CARD_PRINT_BOOKLET', 1, 'Printed official 5-page booklet for Card N° 389107', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:06:58'),
(3, 1, 'admin', 'CARD_PRINT_PVC', 1, 'Printed biometric PVC smart card for Card N° 389107', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:06:58'),
(4, 1, 'admin', 'CARD_PRINT_CERTIFICATE', 1, 'Printed official residence certificate for Card N° 389107', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:06:58'),
(5, 1, 'admin', 'LOGIN_SUCCESS', NULL, 'User admin logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:07:57'),
(6, 1, 'admin', 'CARD_CREATED', 2, 'Enrolled Residence Card N° 389109 for KOUAME, KOFFI JEAN', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:07:57'),
(7, 1, 'admin', 'LOGIN_SUCCESS', NULL, 'User admin logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:08:10'),
(8, 1, 'admin', 'CARD_RENEWED', 2, 'Granted Renewal #1 until 2030-02-14 by Adamma Eze (Comptroller General / Admin)', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:08:10'),
(9, NULL, 'GUEST_OR_SYSTEM', 'CARD_VERIFIED_LOOKUP', 2, 'Inspection lookup verified for Card N° 389109', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:08:26'),
(10, NULL, 'GUEST_OR_SYSTEM', 'LOGIN_FAILED', NULL, 'Failed login attempt for username: 10007', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 04:13:38'),
(11, 1, 'admin', 'LOGIN_SUCCESS', NULL, 'User admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 04:14:03'),
(12, 1, 'admin', 'LOGIN_SUCCESS', NULL, 'User admin logged in successfully', '127.0.0.1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:25:15'),
(13, 1, 'admin', 'LOGOUT', NULL, 'User admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 04:40:01'),
(14, NULL, 'GUEST_OR_SYSTEM', 'LOGIN_FAILED', NULL, 'Failed login attempt for username: 39031', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 04:40:15'),
(15, 1, 'admin', 'LOGIN_SUCCESS', NULL, 'User admin logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 04:40:18'),
(16, 1, 'admin', 'LOGOUT', NULL, 'User admin logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 04:51:04'),
(17, NULL, 'GUEST_OR_SYSTEM', 'LOGIN_FAILED', NULL, 'Failed login attempt for Service No: 39031', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9168', '2026-09-04 04:53:01'),
(18, NULL, 'GUEST_OR_SYSTEM', 'LOGIN_FAILED', NULL, 'Failed login attempt for Service No: 39031', '::1', 'CLI', '2026-09-04 04:54:08'),
(19, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 04:54:54'),
(20, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 04:58:20'),
(21, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 04:58:38'),
(22, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 04:58:48'),
(23, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 04:58:57'),
(24, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 04:59:16'),
(26, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 05:09:48'),
(27, 1, '39031', 'CARD_PRINT_BOOKLET', 5, 'Printed official 5-page booklet for Card N° 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 05:15:05'),
(28, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 05:24:20'),
(29, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 05:24:40'),
(30, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 05:46:10'),
(31, 1, '39031', 'CARD_PRINT_PVC', 5, 'Printed biometric PVC smart card for Card N° 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 06:18:06'),
(32, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 06:23:38'),
(33, 1, '39031', 'CARD_CREATED', 7, 'Enrolled Residence Card N° 389999 for DIOP, MAMADOU', '::1', 'CLI', '2026-09-04 06:23:38'),
(34, 1, '39031', 'CARD_CREATED', 8, 'Enrolled Residence Card N° 390000 for DIOP, MAMADOU', '::1', 'CLI', '2026-09-04 06:24:04'),
(35, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:03:27'),
(36, 1, '39031', 'CARD_CREATED', 9, 'Enrolled Residence Card N° 989944 for EZE, ADAMMA TEST', '::1', 'CLI', '2026-09-04 07:03:27'),
(37, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:04:14'),
(38, 1, '39031', 'CARD_CREATED', 10, 'Enrolled Residence Card N° 983264 for EZE, ADAMMA TEST', '::1', 'CLI', '2026-09-04 07:04:15'),
(39, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card N° 989944', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 07:21:34'),
(40, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:44:21'),
(41, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:44:33'),
(42, 1, '39031', 'CARD_CREATED', 11, 'Enrolled Residence Card No. 989284 for EZE, ADAMMA TEST', '::1', 'CLI', '2026-09-04 07:44:34'),
(43, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:44:44'),
(44, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card No. 989944', '::1', 'CLI', '2026-09-04 07:44:44'),
(45, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:44:53'),
(46, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card No. 989944', '::1', 'CLI', '2026-09-04 07:44:53'),
(47, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 07:46:30'),
(48, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card No. 989944', '::1', 'CLI', '2026-09-04 07:46:31'),
(49, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:04:14'),
(50, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:04:20'),
(51, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card No. 989944', '::1', 'CLI', '2026-09-04 08:04:20'),
(52, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:15:52'),
(53, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:15:58'),
(54, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card No. 989944', '::1', 'CLI', '2026-09-04 08:15:58'),
(55, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:15:59'),
(56, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:54:53'),
(57, 1, '39031', 'USER_CREATED', NULL, 'Created new user account: 99881 (IBRAHIM MOHAMMED ALI) with role IssuingOfficer', '::1', 'CLI', '2026-09-04 08:54:53'),
(58, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:55:40'),
(59, 1, '39031', 'USER_CREATED', NULL, 'Created new user account: 99881 (IBRAHIM MOHAMMED ALI) with role IssuingOfficer', '::1', 'CLI', '2026-09-04 08:55:40'),
(60, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:56:11'),
(61, 1, '39031', 'USER_CREATED', NULL, 'Created new user account: 99881 (IBRAHIM MOHAMMED ALI) with role IssuingOfficer', '::1', 'CLI', '2026-09-04 08:56:11'),
(62, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:56:33'),
(63, 1, '39031', 'USER_CREATED', NULL, 'Created new user account: 99881 (IBRAHIM MOHAMMED ALI) with role IssuingOfficer', '::1', 'CLI', '2026-09-04 08:56:33'),
(64, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:56:43'),
(65, 1, '39031', 'CARD_PRINT_BOOKLET', 9, 'Printed official 5-page booklet for Card No. 989944', '::1', 'CLI', '2026-09-04 08:56:43'),
(66, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:56:44'),
(67, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:56:44'),
(68, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 08:56:59'),
(69, 1, '39031', 'EXPORT_CSV', NULL, 'Exported residence cards CSV report', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 09:04:50'),
(70, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:07:36'),
(71, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:21:27'),
(72, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:21:50'),
(73, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:25:25'),
(74, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:25:38'),
(75, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:26:25'),
(76, 1, '39031', 'PROFILE_PHOTO_UPDATED', NULL, 'User Adamma Eze (39031) uploaded a new profile photo.', '::1', 'CLI', '2026-09-04 09:26:25'),
(77, 1, '39031', 'PROFILE_PHOTO_UPDATED', NULL, 'User Adamma Eze (39031) uploaded a new profile photo.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 09:26:27'),
(78, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-04 09:26:31'),
(79, 1, '39031', 'CARD_PRINT_PVC', 9, 'Printed biometric PVC smart card for Card No. 989944', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 09:33:28'),
(80, 1, '39031', 'PROFILE_PHOTO_UPDATED', NULL, 'User Adamma Eze (39031) uploaded a new profile photo.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 09:55:56'),
(81, NULL, 'GUEST_OR_SYSTEM', 'LOGIN_FAILED', NULL, 'Failed login attempt for Service No: 39031', '127.0.0.1', 'CLI', '2026-09-05 00:06:14'),
(82, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:09:30'),
(83, NULL, 'GUEST_OR_SYSTEM', 'LOGIN_FAILED', NULL, 'Failed login attempt for Service No: 39031', '127.0.0.1', 'CLI', '2026-09-05 00:11:20'),
(84, 1, '39031', 'LOGOUT', NULL, 'User 39031 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:20:54'),
(85, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:21:55'),
(86, NULL, 'GUEST_OR_SYSTEM', 'FIELD_QR_VERIFIED', 12, 'Field inspection check on Card No. 995092', '127.0.0.1', 'CLI', '2026-09-05 00:35:52'),
(87, NULL, 'GUEST_OR_SYSTEM', 'FIELD_QR_VERIFIED', 12, 'Field inspection check on Card No. 995092', '127.0.0.1', 'CLI', '2026-09-05 00:35:52'),
(88, 1, '39031', 'FIELD_QR_VERIFIED', 14, 'Field inspection check on Card No. 991580', '127.0.0.1', 'CLI', '2026-09-05 00:36:10'),
(89, 1, '39031', 'FIELD_QR_VERIFIED', 14, 'Field inspection check on Card No. 991580', '127.0.0.1', 'CLI', '2026-09-05 00:36:10'),
(90, 1, '39031', 'EXPORT_CARDS_CSV', NULL, 'Exported 10 residence cards to Excel CSV', '127.0.0.1', 'CLI', '2026-09-05 00:36:50'),
(91, 1, '39031', 'EXPORT_REPORT_CSV', NULL, 'Exported comprehensive residency report CSV for 2020-01-01 to 2026-09-05', '127.0.0.1', 'CLI', '2026-09-05 00:36:50'),
(92, 1, '39031', 'EXPORT_AUDIT_CSV', NULL, 'Exported 90 security audit log entries to CSV', '127.0.0.1', 'CLI', '2026-09-05 00:36:51'),
(93, NULL, 'GUEST_OR_SYSTEM', 'FIELD_QR_VERIFIED', 1, 'Field inspection check on Card No. 389107', '::1', 'curl/8.21.0', '2026-09-05 00:37:14'),
(94, 1, '39031', 'CARD_APPROVED', 5, 'Card approved by Comptroller Adamma Eze (39031)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:20:03'),
(95, 1, '39031', 'CARD_PRINT_CERTIFICATE', 5, 'Printed official residence certificate for Card No. 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:20:15'),
(96, 1, '39031', 'CARD_PRINT_PVC', 5, 'Printed biometric PVC smart card for Card No. 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:20:30'),
(97, 1, '39031', 'CARD_PRINT_BOOKLET', 5, 'Printed official 5-page booklet for Card No. 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:21:12'),
(98, 1, '39031', 'CARD_PRINT_CERTIFICATE', 5, 'Printed official residence certificate for Card No. 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:21:44'),
(99, 1, '39031', 'CARD_PRINT_BOOKLET', 5, 'Printed official 5-page booklet for Card No. 389112', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:22:31'),
(100, 1, '39031', 'CARD_CREATED', 16, 'Enrolled Residence Card No. 987430 for EZE, ADAMMA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:55:51'),
(101, 1, '39031', 'CARD_PRINT_PVC', 16, 'Printed biometric PVC smart card for Card No. 987430', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 20:56:15'),
(102, 1, '39031', 'CARD_PRINT_PVC', 16, 'Printed biometric PVC smart card for Card No. 987430', '127.0.0.1', 'CLI', '2026-09-05 21:04:06'),
(103, 3, '19102', 'CARD_CREATED', 17, 'Enrolled Residence Card No. 888123 for TESTAPPROVAL, JOHN - Sent for approval', '127.0.0.1', 'CLI', '2026-09-05 21:08:59'),
(104, 1, '39031', 'CARD_CREATED', 18, 'Enrolled Residence Card No. 987431 for EZE, ADAMMA - Sent for approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:18:24'),
(105, 1, '39031', 'CARD_QUERIED', 18, 'Card queried: Biometric Photo Discrepancy: Recapture photo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:19:50'),
(106, 1, '39031', 'WATCHLIST_FLAGGED', 18, 'Holder EZE flagged on Statutory Watchlist: National Security Interdiction Directive', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:20:30'),
(107, 1, '39031', 'CARD_PRINT_BOOKLET', 18, 'Printed official 5-page booklet for Card No. 987431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:21:27'),
(108, 1, '39031', 'CARD_PRINT_PVC', 18, 'Printed biometric PVC smart card for Card No. 987431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:22:02'),
(109, 1, '39031', 'WATCHLIST_REMOVED', 18, 'Watchlist stop order removed for Card No. 987431 by Adamma Eze', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:25:24'),
(110, 1, '39031', 'CARD_PRINT_PVC', 18, 'Printed biometric PVC smart card for Card No. 987431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:26:25'),
(111, 1, '39031', 'CARD_PRINT_PVC', 18, 'Printed biometric PVC smart card for Card No. 987431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:29:42'),
(112, 1, 'GUEST_OR_SYSTEM', 'CARD_PRINT_PVC', 1, 'Printed biometric PVC smart card for Card No. 389107', '127.0.0.1', 'CLI', '2026-09-05 21:46:50'),
(113, 1, '39031', 'CARD_PRINT_PVC', 18, 'Printed biometric PVC smart card for Card No. 987431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 21:54:03'),
(114, 1, '39031', 'CARD_UPDATED', 18, 'Residence Card No. 987431 particulars updated by Officer 39031', '127.0.0.1', 'CLI', '2026-09-05 22:27:16'),
(115, 1, '39031', 'CARD_UPDATED', 16, 'Residence Card No. 987430 particulars updated by Officer 39031', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 22:38:45'),
(116, 1, '39031', 'LOGOUT', NULL, 'User 39031 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:10:25'),
(117, 2, '24820', 'LOGIN_SUCCESS', NULL, 'Officer ACI Ibrahim Musa (Head, Residence Section) (24820) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:10:49'),
(118, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-09 09:16:35'),
(119, 2, '24820', 'LOGOUT', NULL, 'User 24820 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:19:08'),
(120, 3, '19102', 'LOGIN_SUCCESS', NULL, 'Officer DSI Ngozi Adeleke (Issuance Desk) (19102) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:19:27'),
(121, 3, '19102', 'CARD_CREATED', 19, 'Enrolled Residence Card No. 987432 for EZE, JOHNSON - Sent for approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 10:05:44'),
(122, 1, '39031', 'LOGOUT', NULL, 'User 39031 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-09 10:05:54'),
(123, 2, '24820', 'LOGIN_SUCCESS', NULL, 'Officer Ibrahim Musa (24820) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-09 10:06:43'),
(124, 2, '24820', 'CARD_APPROVED', 19, 'Card approved by Ibrahim Musa (24820)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-09 10:09:29'),
(125, 2, '24820', 'CARD_CREATED', 20, 'Enrolled Residence Card No. 833998 for GGGGGGGGGG, GGGGGGGGGG - Sent for approval', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-09 10:32:22'),
(126, 3, '19102', 'LOGOUT', NULL, 'User 19102 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 10:34:18'),
(127, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 10:34:25'),
(128, 1, '39031', 'CARD_PRINT_PVC', 14, 'Printed biometric PVC smart card for Card No. 991580', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 10:35:01'),
(129, 1, '39031', 'CARD_PRINT_BOOKLET', 20, 'Printed official 5-page booklet for Card No. 833998', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 10:42:07'),
(130, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 11:53:24'),
(131, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-10 08:44:18'),
(132, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-10 09:02:19'),
(133, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'CLI', '2026-09-10 09:10:22'),
(134, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-11 08:11:51'),
(135, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 07:28:50'),
(136, 2, '24820', 'LOGIN_SUCCESS', NULL, 'Officer Ibrahim Musa (24820) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '2026-09-14 07:31:09'),
(137, 2, '24820', 'LOGOUT', NULL, 'User 24820 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '2026-09-14 07:31:21'),
(138, 1, '39031', 'CARD_PRINT_PVC', 18, 'Printed biometric PVC smart card for Card No. 987431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 08:55:27'),
(139, 1, '39031', 'EXPORT_APPLICATIONS_CSV', NULL, 'Exported 2 applications to CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 09:44:20'),
(140, 1, '39031', 'SUPERADMIN_APPLICATION_CREATED', 10, 'Super Administrator Adamma Eze created application No. RC-2026-794981 for EZE, ADAMMA BLESSING', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 10:03:56'),
(141, 1, '39031', 'LOGOUT', NULL, 'User 39031 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 13:40:55'),
(142, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 13:52:38'),
(143, 1, '39031', 'CARD_PRINT_PVC', 19, 'Printed biometric PVC smart card for Card No. 987432', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 13:52:48'),
(144, 1, '39031', 'LOGOUT', NULL, 'User 39031 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 13:53:34'),
(145, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 14:00:40'),
(146, 1, '39031', 'LOGOUT', NULL, 'User 39031 logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 17:30:20'),
(147, 1, 'GUEST_OR_SYSTEM', 'SUPERADMIN_APPLICATION_CREATED', 15, 'Super Administrator Adamma Eze created application No. RC-2026-545687 for SUPERADMINTEST, OFFICER', '127.0.0.1', 'CLI', '2026-09-15 20:08:52'),
(148, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-15 21:16:15'),
(149, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-17 09:09:30'),
(150, 1, '39031', 'APPLICATION_QUERIED', 31, 'Application No. RC-2026-977096 queried by Adamma Eze: ghghjhjh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-17 09:10:53'),
(151, 1, '39031', 'CARD_PRINT_PVC', 19, 'Printed biometric PVC smart card for Card No. 987432', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-17 09:14:02'),
(152, 1, '39031', 'PROFILE_PHOTO_UPDATED', NULL, 'User Adamma Eze (39031) uploaded a new profile photo.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-17 10:47:17'),
(153, 1, '39031', 'APPLICATION_APPROVED', 32, 'Approving Officer Adamma Eze (39031) approved Application No. RC-2026-376645 (EZE, KOBI) for physical biometrics capturing at NIS HQ.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-17 11:02:54'),
(154, 1, '39031', 'LOGIN_SUCCESS', NULL, 'Officer Adamma Eze (39031) logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-18 12:33:37'),
(155, 1, '39031', 'APPLICATION_QUERIED', 34, 'Application No. RC-2026-597358 queried by Adamma Eze: gfnbjjkjuk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-18 12:34:14'),
(156, 1, '39031', 'APPLICATION_APPROVED', 33, 'Approving Officer Adamma Eze (39031) approved Application No. RC-2026-156721 (EZE, KOBI) for physical biometrics capturing at NIS HQ.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-18 12:34:27'),
(157, 1, '39031', 'CARD_PRINT_PVC', 19, 'Printed biometric PVC smart card for Card No. 987432', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-18 12:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `card_renewals`
--

CREATE TABLE `card_renewals` (
  `id` int(11) NOT NULL,
  `card_id` int(11) NOT NULL,
  `renewal_number` int(11) NOT NULL DEFAULT 1,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `renewed_at` varchar(100) NOT NULL,
  `endorsing_officer` varchar(100) NOT NULL,
  `officer_service_no` varchar(50) NOT NULL,
  `fee_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `receipt_number` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 1, 'APPROVAL_REQUEST', 'New Approval Request Received', 'Residence Card No. 389107 (DIOP, MAMADOU LAMINE) enrolled by Ngozi Adeleke requires statutory review.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(2, 1, 'APPROVAL_RECEIVED', 'Card Approved for Issuance', 'Residence Card No. 389109 was APPROVED by Ibrahim Musa. Card authorized for PVC printing.', 'card-details.php?id=2', 0, '2026-09-09 10:15:28'),
(3, 1, 'APPROVAL_SENT', 'Approval Request Sent', 'Residence Card enrollment for 389107 submitted to National Approval Registry.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(4, 2, 'APPROVAL_REQUEST', 'New Approval Request Received', 'Residence Card No. 389107 (DIOP, MAMADOU LAMINE) enrolled by Ngozi Adeleke is awaiting your review.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(5, 2, 'APPROVAL_SENT', 'Approval Decision Sent', 'You approved Residence Card No. 389109 (KOUAME, KOFFI JEAN) for PVC issuance.', 'card-details.php?id=2', 0, '2026-09-09 10:15:28'),
(6, 2, 'CARD_QUERIED', 'Query Directive Issued', 'Query sent to Issuing Officer on Residence Card No. 389107: Discrepancy in biometric photo background.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(7, 3, 'APPROVAL_SENT', 'Approval Request Sent', 'Residence Card No. 389107 (DIOP, MAMADOU LAMINE) was submitted for Comptroller approval.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(8, 3, 'APPROVAL_RECEIVED', 'Card Approved for Issuance', 'Residence Card No. 389109 has been APPROVED by Ibrahim Musa. Ready for PVC printing.', 'card-details.php?id=2', 0, '2026-09-09 10:15:28'),
(9, 3, 'CARD_QUERIED', 'Query Received on Card', 'Query received from Ibrahim Musa on Card No. 389107: Please verify applicant tax clearance certificate.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(10, 4, 'APPROVAL_RECEIVED', 'Card Approval Verified', 'Residence Card No. 389107 verified and active on National Border Registry.', 'card-details.php?id=1', 0, '2026-09-09 10:15:28'),
(11, 1, 'APPROVAL_REQUEST', 'New Approval Request Received', 'Residence Card No. 833998 for GGGGGGGGGG, GGGGGGGGGG enrolled by Ibrahim Musa requires review.', 'card-details.php?id=20', 0, '2026-09-09 10:32:22'),
(12, 2, 'APPROVAL_SENT', 'Approval Request Sent', 'Residence Card No. 833998 for GGGGGGGGGG, GGGGGGGGGG was submitted for approval.', 'card-details.php?id=20', 0, '2026-09-09 10:32:22'),
(13, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-895242 for EZE, ADAMMA (ALBANIA) awaiting statutory vetting.', 'pending-approvals.php', 0, '2026-09-10 22:18:08'),
(14, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-895242 for EZE, ADAMMA (ALBANIA) awaiting statutory vetting.', 'pending-approvals.php', 0, '2026-09-10 22:18:08'),
(15, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-197856 for DOE, JOHN RICHARD (UNITED KINGDOM) awaiting statutory vetting.', 'pending-approvals.php', 0, '2026-09-11 06:49:36'),
(16, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-197856 for DOE, JOHN RICHARD (UNITED KINGDOM) awaiting statutory vetting.', 'pending-approvals.php', 0, '2026-09-11 06:49:36'),
(17, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-987904 for DOE, JOHN RICHARD (UNITED KINGDOM) awaiting review.', 'pending-approvals.php', 1, '2026-09-11 07:49:29'),
(18, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-987904 for DOE, JOHN RICHARD (UNITED KINGDOM) awaiting review.', 'pending-approvals.php', 0, '2026-09-11 07:49:29'),
(19, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-433764 for EZE, ADAMMA (BARBADOS) awaiting review.', 'pending-approvals.php', 0, '2026-09-14 09:06:06'),
(20, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-433764 for EZE, ADAMMA (BARBADOS) awaiting review.', 'pending-approvals.php', 0, '2026-09-14 09:06:06'),
(21, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-577132 for EZE, ADAMMA (RUSSIA) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 13:31:10'),
(22, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-577132 for EZE, ADAMMA (RUSSIA) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 13:31:10'),
(23, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-453603 for SMITH, ROBERT (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 20:03:40'),
(24, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-453603 for SMITH, ROBERT (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 20:03:40'),
(25, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-757416 for PAID, VERIFIED (CANADA) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 20:04:48'),
(26, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-757416 for PAID, VERIFIED (CANADA) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 20:04:48'),
(27, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-221974 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 21:57:44'),
(28, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-221974 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-15 21:57:44'),
(29, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-977096 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-16 06:36:21'),
(30, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-977096 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-16 06:36:21'),
(31, 1, 'APPROVAL_SENT', 'Application Query Issued', 'Query issued on Application No. RC-2026-977096: ghghjhjh', 'pending-approvals.php?status=QUERIED', 0, '2026-09-17 09:10:53'),
(32, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-376645 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-17 10:20:17'),
(33, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-376645 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals.php', 0, '2026-09-17 10:20:17'),
(34, 1, 'APPROVAL_SENT', 'Application Approved', 'You approved Application No. RC-2026-376645 (EZE, KOBI) for biometrics appointment on 2026-09-20.', 'print-appointment-slip?app=RC-2026-376645', 0, '2026-09-17 11:02:54'),
(35, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-156721 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals', 0, '2026-09-17 11:39:44'),
(36, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-156721 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals', 0, '2026-09-17 11:39:44'),
(37, 1, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-597358 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals', 0, '2026-09-17 11:49:03'),
(38, 2, 'APPROVAL_RECEIVED', 'New Online Application Received', 'New Residence Card Application No. RC-2026-597358 for EZE, KOBI (UNITED STATES) awaiting review.', 'pending-approvals', 0, '2026-09-17 11:49:03'),
(39, 1, 'APPROVAL_SENT', 'Application Query Issued', 'Query issued on Application No. RC-2026-597358: gfnbjjkjuk', 'pending-approvals?status=QUERIED', 0, '2026-09-18 12:34:14'),
(40, 1, 'APPROVAL_SENT', 'Application Approved', 'You approved Application No. RC-2026-156721 (EZE, KOBI) for biometrics appointment on 2026-09-20.', 'print-appointment-slip?app=RC-2026-156721', 0, '2026-09-18 12:34:27');

-- --------------------------------------------------------

--
-- Table structure for table `residence_cards`
--

CREATE TABLE `residence_cards` (
  `id` int(11) NOT NULL,
  `card_number` varchar(50) NOT NULL,
  `booklet_number` varchar(50) NOT NULL,
  `issuing_country` varchar(100) NOT NULL DEFAULT 'FEDERAL REPUBLIC OF NIGERIA',
  `statutory_protocol` varchar(150) NOT NULL DEFAULT 'Protocol A/SP1/7/86 dated 1 July 1986',
  `decision_reference` varchar(100) NOT NULL,
  `decision_date` date NOT NULL,
  `approving_authority` varchar(100) NOT NULL DEFAULT 'COMPTROLLER GENERAL OF IMMIGRATION',
  `surname` varchar(100) NOT NULL,
  `forenames` varchar(100) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `nationality` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `place_of_birth` varchar(100) NOT NULL,
  `sex` varchar(20) NOT NULL,
  `height` varchar(30) NOT NULL,
  `complexion` varchar(50) NOT NULL,
  `eye_color` varchar(50) NOT NULL,
  `hair_color` varchar(50) NOT NULL,
  `distinguished_features` varchar(150) NOT NULL DEFAULT 'NONE',
  `profession` varchar(100) NOT NULL,
  `domicile` text NOT NULL,
  `passport_number` varchar(50) NOT NULL,
  `national_id_number` varchar(50) DEFAULT NULL,
  `tax_id_number` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_relation` varchar(50) NOT NULL,
  `emergency_contact_phone` varchar(50) NOT NULL,
  `emergency_contact_address` text NOT NULL,
  `blood_group` varchar(20) NOT NULL DEFAULT 'UNKNOWN',
  `change_of_address` text DEFAULT NULL,
  `issuing_officer_name` varchar(100) NOT NULL,
  `issuing_officer_service_no` varchar(50) NOT NULL,
  `issuing_officer_signature` text DEFAULT NULL,
  `issued_on` date NOT NULL,
  `issued_at` varchar(100) NOT NULL,
  `expires_on` date NOT NULL,
  `postage_stamp_code` varchar(50) DEFAULT NULL,
  `authority_signature` varchar(100) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ISSUED',
  `verification_token` varchar(64) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revocation_reason` varchar(255) DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `is_watchlisted` tinyint(1) NOT NULL DEFAULT 0,
  `watchlist_reason` varchar(255) DEFAULT NULL,
  `watchlisted_by` int(11) DEFAULT NULL,
  `watchlisted_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residence_cards`
--

INSERT INTO `residence_cards` (`id`, `card_number`, `booklet_number`, `issuing_country`, `statutory_protocol`, `decision_reference`, `decision_date`, `approving_authority`, `surname`, `forenames`, `photo_path`, `nationality`, `date_of_birth`, `place_of_birth`, `sex`, `height`, `complexion`, `eye_color`, `hair_color`, `distinguished_features`, `profession`, `domicile`, `passport_number`, `national_id_number`, `tax_id_number`, `emergency_contact_name`, `emergency_contact_relation`, `emergency_contact_phone`, `emergency_contact_address`, `blood_group`, `change_of_address`, `issuing_officer_name`, `issuing_officer_service_no`, `issuing_officer_signature`, `issued_on`, `issued_at`, `expires_on`, `postage_stamp_code`, `authority_signature`, `status`, `verification_token`, `created_by`, `created_at`, `updated_at`, `revocation_reason`, `revoked_by`, `revoked_at`, `is_watchlisted`, `watchlist_reason`, `watchlisted_by`, `watchlisted_at`, `approved_by`, `approved_at`, `rejection_reason`) VALUES
(9, '989944', 'RC-989944/26', 'FEDERAL REPUBLIC OF NIGERIA', '', '', '2026-09-04', 'COMPTROLLER GENERAL OF IMMIGRATION', 'EZE', 'ADAMMA TEST', NULL, 'GHANAIAN', '1990-05-15', 'ACCRA', 'F', '1.70 M', 'FAIR', 'BROWN', 'BLACK', 'NONE', 'SOFTWARE ARCHITECT', 'PLOT 402, VICTORIA ISLAND, LAGOS', 'GH301101', '', '', 'CHINEDU EZE', 'SPOUSE', '+2348030001122', 'PLOT 402, VICTORIA ISLAND, LAGOS', 'O+', 'NO CHANGE RECORDED', 'ADAMMA EZE', '39031', 'ELECTRONICALLY SEALED', '2026-09-04', 'NIS HQ', '2028-09-03', 'NIS-NSPMC-2026-639', 'COMPTROLLER GENERAL', 'ISSUED', '1e9d3ef816ba2d5dda0dfc79325e7cec', 1, '2026-09-04 07:03:27', '2026-09-14 07:22:22', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(16, '987430', 'RC-987430/26', 'FEDERAL REPUBLIC OF NIGERIA', '', '', '2026-09-05', 'COMPTROLLER GENERAL OF IMMIGRATION', 'EZE', 'ADAMMA', 'uploads/photos/photo_987430_1788647925.png', 'SOUTH AFRICA', '2026-08-30', 'JOS', 'FEMALE', '1.78 M', 'DARK', 'GREY', 'DARK BROWN', 'NONE', 'PUBLIC SERVANT', 'JHJHYUGHVB ZVDH', 'B6556666', '', '', 'KOBI EZE', 'CHILD', '+2348096412228', 'NIGERIA IMMIGRATION SERVICE HEADQUARTERS, SAUKA AIRPORT ROAD', 'A-', 'NO CHANGE RECORDED', 'ADAMMA EZE', '39031', 'ELECTRONICALLY SEALED', '2026-09-05', 'NIS HQ', '2028-09-04', 'NIS-NSPMC-2026-665', 'COMPTROLLER GENERAL', 'ISSUED', '48a9ecd3b28fd0b25f9c9bee82dbdd0f', 1, '2026-09-05 20:55:51', '2026-09-14 07:22:22', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(18, '987431', 'RC-987431/26', 'FEDERAL REPUBLIC OF NIGERIA', '', '', '2026-09-05', 'COMPTROLLER GENERAL OF IMMIGRATION', 'EZE', 'ADAMMA', 'uploads/photos/photo_987431_1788643104.jpg', 'CHAD', '2000-06-20', 'JOS', 'FEMALE', '1.78 M', 'FAIR', 'BROWN', 'BLACK', 'NONE', 'PUBLIC SERVANT', 'MAITAMA, ABUJA', 'B6556662', '', '', 'EMEKA EZE', 'PARENT', '+234 809 641 2228', '5 AMBASSADOR UCHE OKEKE STREET, UNITY ESTATE KARU', 'A+', 'NO CHANGE RECORDED', 'ADAMMA EZE', '39031', 'ELECTRONICALLY SEALED', '2026-09-05', 'NIS HQ', '2028-09-04', 'NIS-NSPMC-2026-704', 'COMPTROLLER GENERAL', 'QUERIED', 'c2a8be4734d2fd0df8c3040d7d3c7d4f', 1, '2026-09-05 21:18:24', '2026-09-14 07:22:22', NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, '2026-09-05 22:19:50', 'Biometric Photo Discrepancy: Recapture photo'),
(19, '987432', 'RC-987432/26', 'FEDERAL REPUBLIC OF NIGERIA', '', '', '2026-09-09', 'COMPTROLLER GENERAL OF IMMIGRATION', 'EZE', 'JOHNSON', 'uploads/photos/photo_987432_1788948344.jpg', 'GHANA', '1997-06-17', 'GHANA', 'MALE', '1.78 M', 'DARK', 'BLACK', 'BLACK', 'NONE', 'IT ENGINEER', 'N0 4 WUSE', 'B56677', '', '', 'BLESSING EZE', 'SPOUSE', '+2348104042971', 'PLOT 812 DURUMI', 'B-', 'NO CHANGE RECORDED', 'NGOZI ADELEKE', '19102', 'ELECTRONICALLY SEALED', '2026-09-09', 'NIS HQ', '2028-09-08', 'NIS-NSPMC-2026-760', 'COMPTROLLER GENERAL', 'ISSUED', 'e82d24027380a927e9dc410244ea67c0', 3, '2026-09-09 10:05:44', '2026-09-14 07:22:22', NULL, NULL, NULL, 0, NULL, NULL, NULL, 2, '2026-09-09 11:09:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `service_number` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'IssuingOfficer',
  `command` varchar(100) NOT NULL DEFAULT 'NIS HQ, SAUKA, ABUJA',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `fullname`, `service_number`, `email`, `password_hash`, `role`, `command`, `is_active`, `created_at`, `last_login`, `photo_path`) VALUES
(1, '39031', 'Adamma Eze', '39031', 'ezeab.nis@gmail.com', '$2y$10$FIwLqNPgmRSQZmY/7j3zOu7y/ADcVQzTADCnmgOldIjaSSDokOnKK', 'SuperAdmin', 'NIS HQ', 1, '2026-09-04 04:06:58', '2026-09-18 13:33:37', 'uploads/avatars/user_1_1789642037.png'),
(2, '24820', 'Ibrahim Musa', '24820', 'i.musa@immigration.gov.ng', '$2y$10$FIwLqNPgmRSQZmY/7j3zOu7y/ADcVQzTADCnmgOldIjaSSDokOnKK', 'ApprovingOfficer', 'NIS HQ', 1, '2026-09-04 04:06:58', '2026-09-14 08:31:09', NULL),
(3, '19102', 'Ngozi Adeleke', '19102', 'n.adeleke@immigration.gov.ng', '$2y$10$FIwLqNPgmRSQZmY/7j3zOu7y/ADcVQzTADCnmgOldIjaSSDokOnKK', 'IssuingOfficer', 'NIS HQ', 1, '2026-09-04 04:06:58', '2026-09-09 10:19:27', NULL),
(4, '17731', 'Chinedu Okoro', '17731', 'c.okoro@immigration.gov.ng', '$2y$10$FIwLqNPgmRSQZmY/7j3zOu7y/ADcVQzTADCnmgOldIjaSSDokOnKK', 'Inspector', 'NIS HQ', 1, '2026-09-04 04:06:58', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_applicant_email` (`email`),
  ADD KEY `idx_applicant_passport` (`passport_number`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_number` (`application_number`),
  ADD UNIQUE KEY `reference_number` (`reference_number`),
  ADD KEY `idx_app_status` (`status`),
  ADD KEY `idx_app_passport` (`passport_number`),
  ADD KEY `idx_app_appnum` (`application_number`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `card_renewals`
--
ALTER TABLE `card_renewals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_id` (`card_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `residence_cards`
--
ALTER TABLE `residence_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `card_number` (`card_number`),
  ADD UNIQUE KEY `verification_token` (`verification_token`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `service_number` (`service_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=158;

--
-- AUTO_INCREMENT for table `card_renewals`
--
ALTER TABLE `card_renewals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `residence_cards`
--
ALTER TABLE `residence_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `card_renewals`
--
ALTER TABLE `card_renewals`
  ADD CONSTRAINT `card_renewals_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `residence_cards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `residence_cards`
--
ALTER TABLE `residence_cards`
  ADD CONSTRAINT `residence_cards_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
