from fastapi import FastAPI

app = FastAPI(title="Lumina AI Worker")

@app.get("/health")
def health():
    return {"status": "ok"}
