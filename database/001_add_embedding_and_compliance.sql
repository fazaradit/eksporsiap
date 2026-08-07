ALTER TABLE hs_code_reference ADD COLUMN embedding JSONB;

CREATE TABLE compliance_checklist (
    id SERIAL PRIMARY KEY,
    negara VARCHAR(100),
    kategori_dokumen VARCHAR(255),
    deskripsi TEXT,
    wajib_opsional VARCHAR(50),
    catatan TEXT,
    sumber_referensi VARCHAR(255)
);
