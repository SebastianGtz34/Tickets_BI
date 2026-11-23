# CLAUDE.md — Tickets

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Archivo inicial: por ahora solo trae la sección de MarkItDown. Corre `/init` en Claude Code para completarlo con la arquitectura del repo.

<!-- markitdown:inicio — sección común a los repos MESS (2026-10-28). Si la cambias, cámbiala en todos. -->
## Leer documentos (PDF, Word, Excel, PowerPoint) con MarkItDown

Cuando tengas que leer un documento binario —del repo (manuales, formatos, plantillas) o uno que te pase el usuario— conviértelo a Markdown con [MarkItDown](https://github.com/microsoft/markitdown) en lugar de intentar abrirlo directo:

```bash
markitdown "Manual Planeacion.pdf"                # imprime el Markdown; léelo de la salida
markitdown "Manual.docx" -o "$TEMP/manual.md"     # si es largo: a un temporal y léelo por partes
```

- Si el comando no existe: `py -m pip install "markitdown[pdf,docx,pptx,xlsx,xls,outlook]"` y reintenta (si `markitdown` no quedó en el PATH, usa `py -m markitdown archivo`). No instales `markitdown[all]`: tarda mucho y trae dependencias de Azure que no usamos.
- Sirve para: PDF con texto, DOCX, PPTX, XLSX/XLS, HTML, CSV, JSON, XML, EPUB, MSG de Outlook y ZIP (convierte cada archivo de adentro).

**Reglas**

1. **Solo para leer y entender.** MarkItDown no es dependencia de este sistema: no lo llames desde PHP ni lo metas en un flujo en tiempo real. La ingesta de documentos para IA vive en MESS-DOC.
2. **Nunca guardes el `.md` generado dentro de esta carpeta**: Apache la sirve y quedaría público. Usa `$TEMP` (`%TEMP%` en cmd).
3. **Excel con cifras → no.** MarkItDown aplana las hojas, mete `NaN` y `Unnamed: n` y pierde los tipos. Para datos numéricos usa pandas o consulta MySQL directo.
4. **PDF que sale vacío o casi vacío** = probablemente escaneado (imagen). Avísalo; no inventes el contenido.
5. Las imágenes incrustadas salen como `![](data:...)`: ignóralas, MarkItDown no las describe.
6. Es de una sola vía: para **editar** un .docx/.xlsx/.pptx no sirve.
<!-- markitdown:fin -->
