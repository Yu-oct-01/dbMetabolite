library(maftools)
library(survival)
library(survminer)

setwd("/Users/yuqilai/GeneMutationAnalysis/DataClean")

args     <- commandArgs(trailingOnly = TRUE)
gene     <- args[1]
subtype  <- args[2]          # ALL / Proneural / Classical / Mesenchymal
out_file <- args[3]
min_n    <- 5                # 突變組 / 野生型組的最少人數

clinical_df <- read.csv("clinical_gbm_final.csv", stringsAsFactors = FALSE)
cohort_df <- clinical_df[clinical_df$MultiOmics_Cohort == 1, ]
colnames(cohort_df)[colnames(cohort_df) == "All_Sample_IDs_MAF"] <- "Tumor_Sample_Barcode"

# 只保留三種正確亞型；若指定亞型則再篩選
cohort_df <- cohort_df[cohort_df$MolecularSubtype_teacher %in% c("Classical", "Mesenchymal", "Proneural"), ]
if (subtype != "ALL") cohort_df <- cohort_df[cohort_df$MolecularSubtype_teacher == subtype, ]

maf <- read.maf(maf = "data_mutations.txt", clinicalData = cohort_df, verbose = FALSE)

# 該基因有突變的樣本
mut_barcodes <- unique(as.character(maf@data$Tumor_Sample_Barcode[maf@data$Hugo_Symbol == gene]))

df <- cohort_df
df$OS_Days   <- as.numeric(df$OS_Days)
df$OS_Status <- as.numeric(df$OS_Status)
df <- df[!is.na(df$OS_Days) & !is.na(df$OS_Status), ]
df$group <- factor(ifelse(df$Tumor_Sample_Barcode %in% mut_barcodes, "Mutant", "Wild-type"),
                   levels = c("Wild-type", "Mutant"))

n_mut <- sum(df$group == "Mutant")
n_wt  <- sum(df$group == "Wild-type")
if (n_mut < min_n || n_wt < min_n) {
    cat(sprintf("INSUFFICIENT: %s 在 %s 中突變組 n=%d、野生型 n=%d，少於 %d 人，無法進行可靠分析。",
                gene, subtype, n_mut, n_wt, min_n), file = stderr())
    quit(status = 2)
}

fit <- survfit(Surv(OS_Days, OS_Status) ~ group, data = df)
cx  <- summary(coxph(Surv(OS_Days, OS_Status) ~ group, data = df))
hr  <- sprintf("HR = %.2f (95%% CI %.2f-%.2f)", cx$conf.int[1, 1], cx$conf.int[1, 3], cx$conf.int[1, 4])

p <- ggsurvplot(
    fit, data = df,
    pval = TRUE, risk.table = TRUE,
    palette = c("#2E9FDF", "#FC4E07"),
    legend.labs = c(paste0("Wild-type (n=", n_wt, ")"), paste0(gene, " Mutant (n=", n_mut, ")")),
    title = paste0(gene, " mutation vs wild-type — ", ifelse(subtype == "ALL", "All subtypes", subtype)),
    subtitle = hr,
    xlab = "Time in Days", ylab = "Survival Probability"
)

png(out_file, width = 1400, height = 1100, res = 150)
print(p)
invisible(dev.off())