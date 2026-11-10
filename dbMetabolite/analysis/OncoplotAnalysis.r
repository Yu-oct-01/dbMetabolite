library(maftools)
library(survival)
library(survminer)

setwd("/Users/yuqilai/Herd/dbMetabolite/analysis")
# 新增
args     <- commandArgs(trailingOnly = TRUE)
subtype  <- if (length(args) >= 1) args[1] else "ALL"
out_file <- if (length(args) >= 2) args[2] else "oncoplot.png"
top_n    <- if (length(args) >= 3) as.integer(args[3]) else 15

# 1. 讀取資料與核心分析集
clinical_df <- read.csv("clinical_gbm_final.csv", stringsAsFactors = FALSE)
cohort_df <- clinical_df[clinical_df$MultiOmics_Cohort == 1, ]

colnames(cohort_df)[colnames(cohort_df) == "All_Sample_IDs_MAF"] <- "Tumor_Sample_Barcode"

# 建立 maftools 物件，並將分子亞型納入臨床資料中
laml <- read.maf(
    maf = "data_mutations.txt",
    clinicalData = cohort_df
)

# 2. 依分子亞型分組檢視突變特徵 (以前 15 個基因爲例)
# maftools 支援透過 clinical.feature 直接比較不同亞型的突变譜
# prognosis.subtypes = clinicalEnrichment(maf = laml, clinicalFeature = 'MolecularSubtype_teacher')
# print(prognosis.subtypes$results)


if (subtype == "ALL") {
    maf_plot <- laml
} else {
    samples <- cohort_df$Tumor_Sample_Barcode[cohort_df$MolecularSubtype_teacher == subtype]
    if (length(samples) == 0) stop(paste("No samples for subtype:", subtype))
    maf_plot <- subsetMaf(maf = laml, tsb = samples)
}

png(out_file, width = 1600, height = 1200, res = 150)
oncoplot(maf = maf_plot, top = top_n)
invisible(dev.off())

# 4. 亞型內部基因突變的生存分析（過濾掉非預期的 "2" 類別）
clean_cohort_df <- cohort_df[cohort_df$MolecularSubtype_teacher %in% c("Classical", "Mesenchymal", "Proneural"), ]

# 4. 亞型內部基因突變的生存分析（例如在特定亞型下，TP53 突變對生存率的影響）
# 這裡以整個 cohort 配合 MolecularSubtype 分層的生存曲線為例
fit_subtype <- survfit(Surv(OS_Days, OS_Status) ~ MolecularSubtype_teacher, data = clean_cohort_df)


# dev.new(width = 8, height = 6)
# ggsurvplot(
#     fit_subtype,
#     data = clean_cohort_df,
#     pval = TRUE,
#     risk.table = TRUE,
#     palette = c("#2E9FDF", "#E7B800", "#FC4E07"), # 剛好對應 3 種正確的亞型    
#     title = "Overall Survival across CPTAC GBM Molecular Subtypes",
#     xlab = "Time in Days",
#     ylab = "Survival Probability"
# )