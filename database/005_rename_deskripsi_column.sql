DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name='hs_code_reference' AND column_name='deskripsi') THEN
        ALTER TABLE hs_code_reference RENAME COLUMN deskripsi TO deskripsi_resmi;
    END IF;
END $$;
