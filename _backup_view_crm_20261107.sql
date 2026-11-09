/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `crm_pelanggan`;
/*!50001 DROP VIEW IF EXISTS `crm_pelanggan`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `crm_pelanggan` AS SELECT 
 1 AS `id_pelanggan`,
 1 AS `nama_pelanggan`,
 1 AS `cod`,
 1 AS `alamat`,
 1 AS `kodepos`,
 1 AS `terdaftar_sejak`,
 1 AS `nama_kunci`,
 1 AS `provinsi`,
 1 AS `kota`,
 1 AS `kecamatan`,
 1 AS `nomor_utama`,
 1 AS `jumlah_nomor`,
 1 AS `jumlah_order`,
 1 AS `qty`,
 1 AS `nilai`,
 1 AS `dibayar`,
 1 AS `sisa`,
 1 AS `order_pertama`,
 1 AS `order_terakhir`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `crm_pelanggan_duplikat`;
/*!50001 DROP VIEW IF EXISTS `crm_pelanggan_duplikat`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `crm_pelanggan_duplikat` AS SELECT 
 1 AS `jenis`,
 1 AS `kunci`,
 1 AS `jumlah_akun`,
 1 AS `contoh_nama`,
 1 AS `id_akun`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `order_crm_pelanggan`;
/*!50001 DROP VIEW IF EXISTS `order_crm_pelanggan`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `order_crm_pelanggan` AS SELECT 
 1 AS `id_pelanggan`,
 1 AS `nama_pelanggan`,
 1 AS `cod`,
 1 AS `alamat`,
 1 AS `kodepos`,
 1 AS `terdaftar_sejak`,
 1 AS `nama_kunci`,
 1 AS `provinsi`,
 1 AS `kota`,
 1 AS `kecamatan`,
 1 AS `nomor_utama`,
 1 AS `jumlah_nomor`,
 1 AS `jumlah_order`,
 1 AS `qty`,
 1 AS `nilai`,
 1 AS `dibayar`,
 1 AS `sisa`,
 1 AS `order_pertama`,
 1 AS `order_terakhir`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `order_crm_pelanggan_duplikat`;
/*!50001 DROP VIEW IF EXISTS `order_crm_pelanggan_duplikat`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `order_crm_pelanggan_duplikat` AS SELECT 
 1 AS `jenis`,
 1 AS `kunci`,
 1 AS `jumlah_akun`,
 1 AS `contoh_nama`,
 1 AS `id_akun`*/;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `crm_pelanggan`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `crm_pelanggan` AS select `p`.`id_pelanggan` AS `id_pelanggan`,`p`.`nama_pelanggan` AS `nama_pelanggan`,`p`.`cod` AS `cod`,`p`.`alamat` AS `alamat`,`p`.`kodepos` AS `kodepos`,`p`.`created_at` AS `terdaftar_sejak`,regexp_replace(trim(lower(`p`.`nama_pelanggan`)),'[[:space:]]+',' ') AS `nama_kunci`,`pv`.`province_name` AS `provinsi`,`ct`.`city_name` AS `kota`,`sd`.`subdistrict_name` AS `kecamatan`,`kon`.`nomor_utama` AS `nomor_utama`,`kon`.`jumlah_nomor` AS `jumlah_nomor`,coalesce(`agg`.`jumlah_order`,0) AS `jumlah_order`,coalesce(`agg`.`qty`,0) AS `qty`,coalesce(`agg`.`nilai`,0) AS `nilai`,coalesce(`agg`.`dibayar`,0) AS `dibayar`,(coalesce(`agg`.`nilai`,0) - coalesce(`agg`.`dibayar`,0)) AS `sisa`,`agg`.`order_pertama` AS `order_pertama`,`agg`.`order_terakhir` AS `order_terakhir` from (((((`order_pelanggan` `p` left join `order_provinces` `pv` on((`pv`.`province_id` = `p`.`provinsi`))) left join `order_cities` `ct` on((`ct`.`city_id` = `p`.`kabupaten`))) left join `order_subdistricts` `sd` on((`sd`.`subdistrict_id` = `p`.`kecamatan`))) left join (select `order_pelanggan_kontak`.`pelanggan_id` AS `pelanggan_id`,min(`order_pelanggan_kontak`.`nomor_pure`) AS `nomor_utama`,count(distinct `order_pelanggan_kontak`.`nomor_pure`) AS `jumlah_nomor` from `order_pelanggan_kontak` group by `order_pelanggan_kontak`.`pelanggan_id`) `kon` on((`kon`.`pelanggan_id` = `p`.`id_pelanggan`))) left join (select `o`.`pemesan_id` AS `id_pelanggan`,count(0) AS `jumlah_order`,sum(coalesce(`b`.`qty`,0)) AS `qty`,sum((coalesce(`b`.`barang`,0) + coalesce(`bc`.`biaya`,0))) AS `nilai`,sum(coalesce(`pm`.`dibayar`,0)) AS `dibayar`,min(`o`.`tanggal_pesan`) AS `order_pertama`,max(`o`.`tanggal_pesan`) AS `order_terakhir` from (((`order_invoice` `o` left join (select `order_dibeli`.`invoice_id` AS `invoice_id`,sum((`order_dibeli`.`qty` * `order_dibeli`.`harga`)) AS `barang`,sum(`order_dibeli`.`qty`) AS `qty` from `order_dibeli` group by `order_dibeli`.`invoice_id`) `b` on((`b`.`invoice_id` = `o`.`id_invoice`))) left join (select `order_biaya`.`invoice_id` AS `invoice_id`,sum(`order_biaya`.`nominal`) AS `biaya` from `order_biaya` group by `order_biaya`.`invoice_id`) `bc` on((`bc`.`invoice_id` = `o`.`id_invoice`))) left join (select `order_pembayaran`.`invoice_id` AS `invoice_id`,sum(`order_pembayaran`.`total_pembayaran`) AS `dibayar` from `order_pembayaran` group by `order_pembayaran`.`invoice_id`) `pm` on((`pm`.`invoice_id` = `o`.`id_invoice`))) where (`o`.`deleted_at` is null) group by `o`.`pemesan_id`) `agg` on((`agg`.`id_pelanggan` = `p`.`id_pelanggan`))) where (`p`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `crm_pelanggan_duplikat`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `crm_pelanggan_duplikat` AS select 'nomor' AS `jenis`,`k`.`nomor_pure` AS `kunci`,count(distinct `k`.`pelanggan_id`) AS `jumlah_akun`,min(`p`.`nama_pelanggan`) AS `contoh_nama`,group_concat(distinct `k`.`pelanggan_id` order by `k`.`pelanggan_id` ASC separator ',') AS `id_akun` from (`order_pelanggan_kontak` `k` join `order_pelanggan` `p` on((`p`.`id_pelanggan` = `k`.`pelanggan_id`))) where ((`p`.`deleted_at` is null) and (char_length(`k`.`nomor_pure`) >= 9)) group by `k`.`nomor_pure` having (count(distinct `k`.`pelanggan_id`) > 1) union all select 'nama' AS `jenis`,regexp_replace(trim(lower(`p`.`nama_pelanggan`)),'[[:space:]]+',' ') AS `kunci`,count(0) AS `jumlah_akun`,min(`p`.`nama_pelanggan`) AS `contoh_nama`,group_concat(`p`.`id_pelanggan` order by `p`.`id_pelanggan` ASC separator ',') AS `id_akun` from `order_pelanggan` `p` where (`p`.`deleted_at` is null) group by regexp_replace(trim(lower(`p`.`nama_pelanggan`)),'[[:space:]]+',' ') having (count(0) > 1) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `order_crm_pelanggan`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `order_crm_pelanggan` AS select `crm_pelanggan`.`id_pelanggan` AS `id_pelanggan`,`crm_pelanggan`.`nama_pelanggan` AS `nama_pelanggan`,`crm_pelanggan`.`cod` AS `cod`,`crm_pelanggan`.`alamat` AS `alamat`,`crm_pelanggan`.`kodepos` AS `kodepos`,`crm_pelanggan`.`terdaftar_sejak` AS `terdaftar_sejak`,`crm_pelanggan`.`nama_kunci` AS `nama_kunci`,`crm_pelanggan`.`provinsi` AS `provinsi`,`crm_pelanggan`.`kota` AS `kota`,`crm_pelanggan`.`kecamatan` AS `kecamatan`,`crm_pelanggan`.`nomor_utama` AS `nomor_utama`,`crm_pelanggan`.`jumlah_nomor` AS `jumlah_nomor`,`crm_pelanggan`.`jumlah_order` AS `jumlah_order`,`crm_pelanggan`.`qty` AS `qty`,`crm_pelanggan`.`nilai` AS `nilai`,`crm_pelanggan`.`dibayar` AS `dibayar`,`crm_pelanggan`.`sisa` AS `sisa`,`crm_pelanggan`.`order_pertama` AS `order_pertama`,`crm_pelanggan`.`order_terakhir` AS `order_terakhir` from `crm_pelanggan` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `order_crm_pelanggan_duplikat`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `order_crm_pelanggan_duplikat` AS select `crm_pelanggan_duplikat`.`jenis` AS `jenis`,`crm_pelanggan_duplikat`.`kunci` AS `kunci`,`crm_pelanggan_duplikat`.`jumlah_akun` AS `jumlah_akun`,`crm_pelanggan_duplikat`.`contoh_nama` AS `contoh_nama`,`crm_pelanggan_duplikat`.`id_akun` AS `id_akun` from `crm_pelanggan_duplikat` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

